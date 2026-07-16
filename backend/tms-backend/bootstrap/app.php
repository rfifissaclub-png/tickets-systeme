<?php

use App\Exceptions\FileInfectedException;
use App\Exceptions\InvalidTicketTransitionException;
use App\Exceptions\TicketAlreadyAssignedException;
use App\Exceptions\TicketIsReadOnlyException;
use App\Exceptions\TicketNotQualifiedException;
use App\Exceptions\VirusScanUnavailableException;
use App\Http\Middleware\PrimeSanctumAuthentication;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SetLocale;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Not passed as withRouting()'s `channels:` param: that registers
    // /broadcasting/auth under the "web" (session-cookie) middleware group,
    // which can never authenticate this API's Sanctum bearer-token clients.
    // Registering it explicitly here with auth:sanctum is what actually
    // lets a token-authenticated client subscribe to a private channel.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['api', 'auth.prime', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.prime' => PrimeSanctumAuthentication::class,
            'tenant.context' => ResolveTenantContext::class,
        ]);

        // Every API request negotiates its own locale from Accept-Language —
        // there's no session to remember one in, and unlike tenant.context
        // this doesn't depend on being authenticated first.
        $middleware->appendToGroup('api', SetLocale::class);

        // Laravel's default $middlewarePriority (Illuminate\Foundation\Http\
        // Kernel) lists Illuminate\Contracts\Auth\Middleware\
        // AuthenticatesRequests — the contract `auth:sanctum`'s Authenticate
        // middleware implements — far ahead of anything not in that list.
        // The priority sort silently hoists any *prioritized* middleware
        // ahead of unlisted ones regardless of the order routes declare
        // them in, so without this, `auth:sanctum` actually ran *before*
        // auth.prime despite auth.prime being listed first on every route —
        // defeating the RLS-bypass priming entirely. Only ever surfaced by
        // serving a real HTTP request: route:list -v shows the *declared*
        // order, not the sorted one actually executed, and Pest's HTTP
        // testing helpers don't go through the same built-in-server pipeline
        // ordering concerns surface identically either way, but here it
        // hides for the same reason as the RLS bug itself — a pre-bound
        // tenant context. See PrimeSanctumAuthentication's own docblock.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: PrimeSanctumAuthentication::class,
        );

        // Same reordering hazard, one step later in the pipeline:
        // Illuminate\Routing\Middleware\SubstituteBindings (implicit route
        // model binding, e.g. the {ticket} in /tickets/{ticket}/comments)
        // is *also* in the default priority list, ahead of anything
        // unlisted — so ResolveTenantContext (tenant.context), also
        // unlisted, was being hoisted-past the same way auth.prime was,
        // and implicit bindings were resolving Ticket::query() before any
        // tenant was bound. RLS then made every such lookup find zero rows,
        // which Laravel reports as a plain 404 on the route, not a 401/403
        // — easy to mistake for a routing mistake instead of an ordering
        // one. Only surfaced by a real request against a route with a
        // bound route-model parameter; /ticket-pool/{ticket}/pull takes a
        // raw string on purpose (see its controller) and never hit this.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveTenantContext::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                message: __('messages.validation_failed'),
                status: 422,
                errors: $e->errors(),
                code: 'validation_failed',
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: __('messages.http.unauthenticated'), status: 401, code: 'unauthenticated');
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: $e->getMessage() ?: __('messages.http.forbidden'), status: 403, code: 'forbidden');
        });

        $exceptions->render(function (TicketAlreadyAssignedException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: $e->getMessage(), status: 409, code: 'ticket_already_assigned');
        });

        $exceptions->render(function (TicketIsReadOnlyException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: $e->getMessage(), status: 409, code: 'ticket_read_only');
        });

        $exceptions->render(function (TicketNotQualifiedException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: $e->getMessage(), status: 403, code: 'ticket_not_qualified');
        });

        $exceptions->render(function (InvalidTicketTransitionException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: $e->getMessage(), status: 422, code: 'invalid_ticket_transition');
        });

        $exceptions->render(function (FileInfectedException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: $e->getMessage(), status: 422, code: 'file_infected');
        });

        // 503, not 500: this is the antivirus scanner being unreachable, not
        // an application bug — a client (or ops, via monitoring) should be
        // able to tell "try again shortly" apart from "something broke".
        $exceptions->render(function (VirusScanUnavailableException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: __('messages.attachments.scan_unavailable'), status: 503, code: 'virus_scan_unavailable');
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: __('messages.http.not_found'), status: 404, code: 'not_found');
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: __('messages.http.not_found'), status: 404, code: 'not_found');
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: __('messages.http.rate_limited'), status: 429, code: 'rate_limited');
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: $e->getMessage() ?: __('messages.http.http_error'), status: $e->getStatusCode(), code: 'http_error');
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') || app()->hasDebugModeEnabled()) {
                return null;
            }

            return ApiResponse::error(message: __('messages.http.server_error'), status: 500, code: 'server_error');
        });
    })->create();
