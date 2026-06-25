<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\GeminiTtsConfigController;
use App\Http\Controllers\Api\V1\Public\Tts\TtsController;
use App\Http\Controllers\Api\V1\RecaptchaConfigController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Version 1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void {

    // ─── إعداد reCAPTCHA العام (بدون مصادقة، لا أسرار) ────────────────
    Route::get('recaptcha/config', RecaptchaConfigController::class);

    // ─── Google Gemini TTS العامّ — توفّر الميزة (بلا أسرار) + توليد الصوت (محكوم بالإعدادات) ──
    Route::get('tts/config', GeminiTtsConfigController::class);
    Route::post('tts/speak', [TtsController::class, 'speak'])->middleware('throttle:public.tts');

    // ─── Public Authentication (guest) ────────────────────────────────
    Route::prefix('auth')->group(base_path('routes/api/v1/auth.php'));

    // ─── Public API ───────────────────────────────────────────────────
    Route::group([], base_path('routes/api/v1/public.php'));

    // ─── Admin Auth (guest) — login, forgot, reset ────────────────────
    // بدون middleware — التحقق من الدور داخل AdminLoginAction مباشرة
    Route::prefix('admin/auth')->group(base_path('routes/api/v1/admin-auth.php'));

    // ─── Admin API (fully protected) ──────────────────────────────────
    // auth:sanctum → abilities:admin → active → role:...
    Route::prefix('admin')
        ->middleware([
            'auth:sanctum',
            'abilities:admin',
            'active',
            'role:super_admin|editor|reviewer|moderator|social_media_manager|journalist|contributor',
        ])
        ->group(base_path('routes/api/v1/admin.php'));
});
