<?php

use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context'])->group(function () {
    Route::get('/me', MeController::class)->name('me');
});
