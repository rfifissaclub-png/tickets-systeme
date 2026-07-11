<?php

use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\ResendVerificationController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\TicketAssignmentController;
use App\Http\Controllers\Api\V1\TicketCommentController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\TicketPoolController;
use App\Http\Controllers\Api\V1\TicketTransitionController;
use App\Http\Controllers\Api\V1\TicketTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', LoginController::class)->middleware('throttle:login')->name('login');
    Route::post('/forgot-password', ForgotPasswordController::class)->middleware('throttle:password-reset')->name('password.email');
    Route::post('/reset-password', ResetPasswordController::class)->middleware('throttle:password-reset')->name('password.reset');

    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:verification'])
        ->name('verification.verify');

    Route::middleware(['auth:sanctum', 'tenant.context'])->group(function () {
        Route::post('/logout', LogoutController::class)->name('logout');

        Route::post('/email/verification-notification', ResendVerificationController::class)
            ->middleware('throttle:verification')
            ->name('verification.send');
    });
});

Route::middleware(['auth:sanctum', 'tenant.context'])->group(function () {
    Route::get('/me', MeController::class)->name('me');

    Route::get('/ticket-types', [TicketTypeController::class, 'index'])->name('ticket-types.index');
    Route::post('/ticket-types', [TicketTypeController::class, 'store'])->name('ticket-types.store');
    Route::patch('/ticket-types/{ticketType}', [TicketTypeController::class, 'update'])->name('ticket-types.update');
    Route::delete('/ticket-types/{ticketType}', [TicketTypeController::class, 'destroy'])->name('ticket-types.destroy');

    Route::get('/ticket-pool', [TicketPoolController::class, 'index'])->name('ticket-pool.index');
    Route::post('/ticket-pool/{ticket}/pull', [TicketPoolController::class, 'pull'])->name('ticket-pool.pull');

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::patch('/tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');

    Route::patch('/tickets/{ticket}/status', TicketTransitionController::class)->name('tickets.status');
    Route::patch('/tickets/{ticket}/assign', TicketAssignmentController::class)->name('tickets.assign');

    Route::get('/tickets/{ticket}/comments', [TicketCommentController::class, 'index'])->name('tickets.comments.index');
    Route::post('/tickets/{ticket}/comments', [TicketCommentController::class, 'store'])->name('tickets.comments.store');
});
