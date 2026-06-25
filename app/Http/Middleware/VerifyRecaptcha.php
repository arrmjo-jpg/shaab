<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Recaptcha\RecaptchaVerifier;
use App\Support\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * حماية reCAPTCHA للنماذج المعرّضة (auth).
 * مُعطّلة بالكامل إذا recaptcha_enabled=false (gated — لا يكسر شيئاً).
 *
 * الاستخدام: ->middleware('recaptcha:<action>')
 * الـ action لازم يطابق ما تنفّذه الواجهة في grecaptcha (v3).
 */
class VerifyRecaptcha
{
    public function __construct(private readonly RecaptchaVerifier $verifier) {}

    public function handle(Request $request, Closure $next, string $action = 'default'): Response
    {
        if (! $this->verifier->enabled()) {
            return $next($request);
        }

        $token = (string) $request->input('recaptcha_token', '');

        if (! $this->verifier->verify($token, $request->ip(), $action)) {
            return ApiResponse::error(__('auth.recaptcha_failed'), [], 422);
        }

        return $next($request);
    }
}
