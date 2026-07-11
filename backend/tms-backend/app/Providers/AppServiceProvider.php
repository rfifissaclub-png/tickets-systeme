<?php

namespace App\Providers;

use App\Enums\RoleName;
use App\Listeners\LogRoleOrPermissionChange;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Logout::class, LogSuccessfulLogout::class);
        Event::listen(RoleAttachedEvent::class, [LogRoleOrPermissionChange::class, 'handleRoleAttached']);
        Event::listen(RoleDetachedEvent::class, [LogRoleOrPermissionChange::class, 'handleRoleDetached']);
        Event::listen(PermissionAttachedEvent::class, [LogRoleOrPermissionChange::class, 'handlePermissionAttached']);
        Event::listen(PermissionDetachedEvent::class, [LogRoleOrPermissionChange::class, 'handlePermissionDetached']);

        // Super Admin is a platform-level role that authorization checks
        // elsewhere (policies, permission checks) should never need to know
        // about individually — this is the single place that grants it
        // universal access, everything else stays tenant/permission scoped.
        Gate::before(fn (User $user, string $ability) => $user->hasRole(RoleName::SuperAdmin->value) ? true : null);

        $this->configureRateLimiting();

        // The default VerifyEmail notification points at a route literally
        // named "verification.verify". Our routes live under the api.v1.*
        // prefix, so the name is "api.v1.auth.verification.verify" instead —
        // point the notification at the real route rather than renaming it.
        VerifyEmail::createUrlUsing(function (User $notifiable) {
            return URL::temporarySignedRoute(
                'api.v1.auth.verification.verify',
                now()->addMinutes(60),
                ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())],
            );
        });

        // Without this, ResetPassword's default resetUrl() calls
        // route('password.reset', ...) — a route that doesn't exist in this
        // API-only app (there's no server-rendered reset form) — and throws
        // as soon as the notification is actually rendered. Point it at the
        // frontend's reset-password page instead, carrying the tenant slug
        // so ResetPasswordController's tenant-scoped lookup has what it
        // needs (Password::sendResetLink was called with tenant_id, but the
        // frontend form re-derives the slug from this link, not the id).
        ResetPassword::createUrlUsing(function (User $notifiable, string $token) {
            $query = http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
                'tenant' => $notifiable->tenant?->slug,
            ]);

            return rtrim(config('app.frontend_url'), '/')."/reset-password?{$query}";
        });
    }

    private function configureRateLimiting(): void
    {
        // Keyed by email+IP, not IP alone: an IP-only limit lets one bad
        // actor lock out every legitimate user sharing that IP (offices,
        // NAT, mobile carriers); email+IP still stops credential stuffing
        // against a single account without that collateral damage.
        $credentialKey = function (Request $request): string {
            return Str::lower((string) $request->input('email')).'|'.$request->ip();
        };

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($credentialKey($request)));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(3)->by($credentialKey($request)));
        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));
    }
}
