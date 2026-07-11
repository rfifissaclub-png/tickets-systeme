<?php

use App\Exceptions\InvalidTicketTransitionException;
use App\Exceptions\TicketAlreadyAssignedException;
use App\Exceptions\TicketIsReadOnlyException;
use App\Http\Middleware\ResolveTenantContext;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.context' => ResolveTenantContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                message: 'The given data was invalid.',
                status: 422,
                errors: $e->errors(),
                code: 'validation_failed',
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: 'Unauthenticated.', status: 401, code: 'unauthenticated');
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: $e->getMessage() ?: 'This action is unauthorized.', status: 403, code: 'forbidden');
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

        $exceptions->render(function (InvalidTicketTransitionException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: $e->getMessage(), status: 422, code: 'invalid_ticket_transition');
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: 'The requested resource was not found.', status: 404, code: 'not_found');
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: 'The requested resource was not found.', status: 404, code: 'not_found');
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: 'Too many requests.', status: 429, code: 'rate_limited');
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(message: $e->getMessage() ?: 'An error occurred.', status: $e->getStatusCode(), code: 'http_error');
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') || app()->hasDebugModeEnabled()) {
                return null;
            }

            return ApiResponse::error(message: 'Server error.', status: 500, code: 'server_error');
        });
    })->create();
