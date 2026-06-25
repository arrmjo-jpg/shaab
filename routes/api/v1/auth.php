<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Public\Auth\AuthController;
use App\Http\Controllers\Api\V1\Public\Auth\SocialAuthConfigController;
use App\Http\Controllers\Api\V1\Public\Auth\SocialAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Authentication Routes — /api/v1/auth/*
|--------------------------------------------------------------------------
*/

// ─── Guest — لا تتطلب token ────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register'])
    ->middleware(['throttle:public.register', 'recaptcha:register']);

Route::post('/login', [AuthController::class, 'login'])
    ->middleware(['throttle:public.login', 'recaptcha:login']);

Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware(['throttle:public.forgot-password', 'recaptcha:forgot_password']);

Route::post('/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware(['throttle:public.forgot-password', 'recaptcha:reset_password']);

// ─── Social login (guest) — config + OAuth redirect/callback (Socialite, stateless) ──
Route::get('/social/config', SocialAuthConfigController::class);
Route::get('/social/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->where('provider', 'google|facebook');
Route::get('/social/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->where('provider', 'google|facebook');

// ─── Authenticated — تتطلب token بـ ability=user ──────────────────────
Route::middleware(['auth:sanctum', 'abilities:user', 'active'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/avatar', [AuthController::class, 'updateAvatar']);
});
