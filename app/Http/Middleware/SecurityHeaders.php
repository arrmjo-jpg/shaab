<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ترويسات أمان دفاعية لاستجابات الـ API (JSON فقط — لا تُصيّر HTML).
 *
 * - CSP صارمة مناسبة لواجهة JSON: لا تُقيّد الـ SPA (مستضاف منفصلاً
 *   ويستهلك JSON عبر fetch؛ CSP على استجابة JSON لا تحكم وثيقة المستدعي).
 * - nosniff يُبطل تخمين النوع (يدعم تشديد رفع الملفات).
 * - DENY/ frame-ancestors يمنع تأطير استجابات الـ API.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), browsing-topics=()',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'",
        ];

        foreach ($headers as $key => $value) {
            if (! $response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        return $response;
    }
}
