<?php

use App\Http\Controllers\Api\V1\AgentSkillController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\ResendVerificationController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\TicketAssignmentController;
use App\Http\Controllers\Api\V1\TicketAttachmentController;
use App\Http\Controllers\Api\V1\TicketCommentController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\TicketPoolController;
use App\Http\Controllers\Api\V1\TicketTransitionController;
use App\Http\Controllers\Api\V1\TicketTypeController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserRoleController;
use Illuminate\Support\Facades\Route;
use App\Models\Ticket;
use App\Services\Tenancy\TenantContext;

// Scoped ticket binder: resolve most {ticket} params to a model across
// tenants (audited bypass) but deliberately skip binding for the ticket
// pool's pull endpoint which expects a raw id string to perform its own
// SELECT ... FOR UPDATE logic inside a transaction.
Route::bind('ticket', function ($value) {
    $route = request()->route();
    $routeName = $route ? $route->getName() : null;

    if ($routeName === 'ticket-pool.pull') {
        return $value;
    }

    return app(TenantContext::class)->bypass('route model binding', fn () => Ticket::withTrashed()->findOrFail($value));
});


Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', LoginController::class)->middleware('throttle:login')->name('login');
    Route::post('/forgot-password', ForgotPasswordController::class)->middleware('throttle:password-reset')->name('password.email');
    Route::post('/reset-password', ResetPasswordController::class)->middleware('throttle:password-reset')->name('password.reset');

    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:verification'])
        ->name('verification.verify');

    Route::middleware(['auth.prime', 'auth:sanctum', 'tenant.context'])->group(function () {
        Route::post('/logout', LogoutController::class)->name('logout');

        Route::post('/email/verification-notification', ResendVerificationController::class)
            ->middleware('throttle:verification')
            ->name('verification.send');
    });
});

Route::middleware(['auth.prime', 'auth:sanctum', 'tenant.context'])->group(function () {
    Route::get('/me', MeController::class)->name('me');

    Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
    Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
    Route::patch('/tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
    Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::patch('/users/{user}/roles', UserRoleController::class)->name('users.roles');
    Route::patch('/users/{user}/skills', AgentSkillController::class)->name('users.skills');

    Route::get('/ticket-types', [TicketTypeController::class, 'index'])->name('ticket-types.index');
    Route::post('/ticket-types', [TicketTypeController::class, 'store'])->name('ticket-types.store');
    Route::patch('/ticket-types/{ticketType}', [TicketTypeController::class, 'update'])->name('ticket-types.update');
    Route::delete('/ticket-types/{ticketType}', [TicketTypeController::class, 'destroy'])->name('ticket-types.destroy');

    Route::get('/ticket-pool', [TicketPoolController::class, 'index'])->name('ticket-pool.index');
    Route::get('/ticket-pool/agents', [TicketPoolController::class, 'agents'])->name('ticket-pool.agents');
    Route::post('/ticket-pool/{ticket}/pull', [TicketPoolController::class, 'pull'])->name('ticket-pool.pull');

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    // Support both PATCH and PUT for backwards compatibility
    Route::patch('/tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
    Route::put('/tickets/{ticket}', [TicketController::class, 'update']);
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');

    Route::patch('/tickets/{ticket}/status', TicketTransitionController::class)->name('tickets.status');
    Route::patch('/tickets/{ticket}/assign', TicketAssignmentController::class)->name('tickets.assign');

    Route::get('/tickets/{ticket}/comments', [TicketCommentController::class, 'index'])->name('tickets.comments.index');
    Route::post('/tickets/{ticket}/comments', [TicketCommentController::class, 'store'])->name('tickets.comments.store');

    Route::get('/tickets/{ticket}/attachments', [TicketAttachmentController::class, 'index'])->name('tickets.attachments.index');
    Route::post('/tickets/{ticket}/attachments', [TicketAttachmentController::class, 'store'])->name('tickets.attachments.store');
    Route::get('/attachments/{attachment}/download', [TicketAttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('/attachments/{attachment}', [TicketAttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Clients and contacts
    Route::get('/clients', [\App\Http\Controllers\Api\V1\ClientController::class, 'index'])->name('clients.index');
    Route::post('/clients', [\App\Http\Controllers\Api\V1\ClientController::class, 'store'])->name('clients.store');
    Route::get('/clients/{client}', [\App\Http\Controllers\Api\V1\ClientController::class, 'show'])->name('clients.show');
    Route::patch('/clients/{client}', [\App\Http\Controllers\Api\V1\ClientController::class, 'update'])->name('clients.update');
    Route::delete('/clients/{client}', [\App\Http\Controllers\Api\V1\ClientController::class, 'destroy'])->name('clients.destroy');

    // Client contacts
    Route::get('/clients/{client}/contacts', [\App\Http\Controllers\Api\V1\ClientContactController::class, 'index'])->name('clients.contacts.index');
    Route::post('/clients/{client}/contacts', [\App\Http\Controllers\Api\V1\ClientContactController::class, 'store'])->name('clients.contacts.store');
    Route::patch('/clients/{client}/contacts/{contact}', [\App\Http\Controllers\Api\V1\ClientContactController::class, 'update'])->name('clients.contacts.update');
    Route::delete('/clients/{client}/contacts/{contact}', [\App\Http\Controllers\Api\V1\ClientContactController::class, 'destroy'])->name('clients.contacts.destroy');

    Route::get('/reports/overview', [ReportController::class, 'overview'])->name('reports.overview');
    Route::get('/reports/tickets-by-agent', [ReportController::class, 'byAgent'])->name('reports.by-agent');
    Route::get('/reports/tickets-by-tenant', [ReportController::class, 'byTenant'])->name('reports.by-tenant');
    Route::get('/reports/ticket-trends', [ReportController::class, 'trends'])->name('reports.trends');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});
