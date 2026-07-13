<?php

namespace App\Providers;

use App\Contracts\VirusScanner;
use App\Enums\RoleName;
use App\Listeners\LogRoleOrPermissionChange;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use App\Models\User;
use App\Services\Attachments\ClamAvScanner;
use App\Services\Attachments\NullVirusScanner;
use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
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

        // Which implementation callers get is decided here, once, from
        // config — FileUploadService always calls the interface and never
        // branches on whether antivirus is turned on. See
        // config/attachments.php for why this defaults to off.
        $this->app->singleton(VirusScanner::class, function () {
            if (! config('attachments.antivirus.enabled')) {
                return new NullVirusScanner;
            }

            return new ClamAvScanner(
                config('attachments.antivirus.host'),
                config('attachments.antivirus.port'),
                config('attachments.antivirus.timeout'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureQueueRlsBypass();

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

        // The signed URL below is what actually gets validated (the
        // `signed` middleware on VerifyEmailController checks its
        // expires/signature query params against id+hash) — but emailing
        // that raw API URL directly means clicking it shows the user bare
        // JSON instead of a page. Same fix as ResetPassword::createUrlUsing
        // below: point the email at a frontend page, carrying the same
        // id/hash/expires/signature as query params, and let the frontend
        // replay them against this exact endpoint via fetch instead of the
        // user's browser navigating straight into the API.
        VerifyEmail::createUrlUsing(function (User $notifiable) {
            $id = $notifiable->getKey();
            $hash = sha1($notifiable->getEmailForVerification());

            $signedUrl = URL::temporarySignedRoute(
                'api.v1.auth.verification.verify',
                now()->addMinutes(60),
                ['id' => $id, 'hash' => $hash],
            );

            $query = (string) parse_url($signedUrl, PHP_URL_QUERY);

            return rtrim(config('app.frontend_url'), '/')."/verify-email?id={$id}&hash={$hash}&{$query}";
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

    /**
     * The queue worker (`php artisan queue:work`, see infra/docker-compose.yml's
     * `queue`/`reverb` services) is a single long-lived process that reuses
     * ONE Postgres connection across every job it ever processes — unlike
     * the per-request PHP-FPM model TenantContext::set()'s own docblock
     * assumes, where a fresh connection makes a leaked session-level `SET`
     * impossible. That connection never has app.tenant_id/app.bypass_rls
     * set at all by default, so RLS blocks every queued job's own model
     * restoration (Illuminate\Queue\SerializesModels re-queries the DB from
     * just the serialized id): a single-model constructor property (e.g.
     * TicketAssignedNotification's $ticket) throws ModelNotFoundException on
     * restoration — a visible job failure — but the notifiable(s) collection
     * SendQueuedNotifications wraps every recipient in uses a plain
     * Collection::get() instead of firstOrFail(), so a blocked row just
     * silently produces an empty collection: no exception, no notification,
     * no error, ever. Every ShouldQueue notification in this app hit this.
     *
     * Bypassed only for the duration of each individual job (mirrors
     * TenantContext::bypass()'s own reasoning for authorized system
     * operations), not the worker's entire lifetime — Queue::before fires
     * before the job payload is even unserialized (see
     * Illuminate\Queue\Worker::process(), which raises this event strictly
     * before calling $job->fire()), so it covers the restoration step too,
     * not just the job's own handle() logic.
     */
    private function configureQueueRlsBypass(): void
    {
        Queue::before(function () {
            DB::statement("SET app.bypass_rls = 'on'");
        });

        Queue::after(function () {
            DB::statement("SET app.bypass_rls = 'off'");
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
