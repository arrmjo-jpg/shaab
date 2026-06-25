<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ترويسات CDN/كاش للمسارات العامة فقط (CDN-aware delivery).
 *
 * - max-age منخفض على المتصفّح (تحديث سريع للقرّاء).
 * - s-maxage عالٍ على الحافة (تقليل ضربات الأصل) + stale-while-revalidate.
 * - لا Vary على Accept-Language: اللغة دائماً في مسار الرابط (/{locale}/…) لا في
 *   الترويسة، فالتبايُن عليها يجزّئ كاش الحافة بلا فائدة (يخفض نسبة الإصابة).
 * - يُفعَّل فقط للاستجابات الناجحة وغير المُحتوى الديناميكي للمصادَقة.
 */
final class PublicCacheHeaders
{
    public function __construct(
        /** ثوانٍ — مدة كاش المتصفح. */
        private readonly int $maxAge = 60,
        /** ثوانٍ — مدة كاش الحافة (CDN). */
        private readonly int $sMaxAge = 300,
        /** ثوانٍ — نافذة stale-while-revalidate على الحافة. */
        private readonly int $staleWhileRevalidate = 86400,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if ($request->method() !== 'GET' && $request->method() !== 'HEAD') {
            return $response;
        }

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        // احترم Cache-Control الذي ضبطه الـ Action صراحةً كقابل للكاش العام (public —
        // استراتيجية TTL المتمايزة عبر CdnTtl)؛ خلاف ذلك (الافتراضي no-cache/private
        // الذي يضعه الإطار) طبّق الافتراضي المتوسّط للبوّابة.
        if (! str_contains((string) $response->headers->get('Cache-Control'), 'public')) {
            $response->headers->set('Cache-Control', sprintf(
                'public, max-age=%d, s-maxage=%d, stale-while-revalidate=%d',
                $this->maxAge,
                $this->sMaxAge,
                $this->staleWhileRevalidate,
            ));
        }

        return $response;
    }
}
