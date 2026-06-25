<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * يُخطِر Google + Bing بتحديث خريطة الموقع (legacy sitemap ping).
 *
 * fire-and-forget: مُجدوَل، محاولة واحدة (tries=1)، معزول الفشل — لا يرمي أبداً في مسار النشر.
 * مرآةُ نمط RevalidateFrontendCacheJob. ⚠️ نقطتا google.com/ping و bing.com/ping أُهملتا فعلياً
 * (قد تُرجِعان 404) — يبقى النداء best-effort بلا أثر سلبي؛ الاكتشاف الحقيقي يتمّ عبر sitemap في
 * robots.txt + Search Console.
 */
class PingSearchEnginesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** محاولة واحدة — إخطار best-effort؛ لا إعادة محاولة (تجنّب عاصفة الطابور). */
    public int $tries = 1;

    public int $timeout = 20;

    public function __construct(public readonly string $sitemapUrl) {}

    public function handle(): void
    {
        if ($this->sitemapUrl === '') {
            return;
        }

        $encoded = urlencode($this->sitemapUrl);
        $endpoints = [
            "https://www.google.com/ping?sitemap={$encoded}",
            "https://www.bing.com/ping?sitemap={$encoded}",
        ];

        foreach ($endpoints as $endpoint) {
            try {
                $response = Http::timeout(8)->get($endpoint);

                if ($response->failed()) {
                    // غير 2xx متوقّع غالباً (النقطة مُهملة) — info لا warning.
                    Log::info('[search-ping] non-2xx', [
                        'endpoint' => $endpoint,
                        'status' => $response->status(),
                    ]);
                }
            } catch (\Throwable $e) {
                // معزول الفشل: لا يكسر النشر ولا يُفجِّر إعادة محاولة.
                Log::warning('[search-ping] failed', [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
