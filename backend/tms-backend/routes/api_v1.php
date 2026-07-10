<?php

use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\ResendVerificationController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\MeController;
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
});
