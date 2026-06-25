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
 * يُخطِر واجهة Next العامة بإبطال وسوم الكاش بعد كتابة محتوى.
 *
 * fire-and-forget: مُجدوَل + معزول الفشل — لا يرمي أبداً داخل مسار الكتابة، ولا يُعيد المحاولة
 * بعاصفة (tries=1). انقطاع الواجهة لا يكسر النشر؛ الصفحات تتعافى عبر ISR/TTL على أسوأ تقدير.
 * نقطة الواجهة (POST /api/revalidate، محميّة بترويسة x-revalidate-secret) تربط الوسوم بـ
 * revalidateTag(). الوسوم تطابق public-frontend/src/core/cache/tags.ts.
 */
class RevalidateFrontendCacheJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** محاولة واحدة — إخطار إبطال best-effort؛ لا إعادة محاولة (تجنّب عاصفة الطابور). */
    public int $tries = 1;

    public int $timeout = 15;

    /** @param array<int,string> $tags */
    public function __construct(public readonly array $tags) {}

    public function handle(): void
    {
        $url = (string) config('services.frontend_revalidate.url', '');
        $secret = (string) config('services.frontend_revalidate.secret', '');

        if ($url === '' || $secret === '' || $this->tags === []) {
            return; // غير مُهيّأ ⇒ لا عملية
        }

        try {
            $response = Http::timeout((int) config('services.frontend_revalidate.timeout', 5))
                ->withHeaders(['x-revalidate-secret' => $secret])
                ->acceptJson()
                ->post($url, ['tags' => $this->tags]);

            if ($response->failed()) {
                Log::warning('[frontend-revalidate] rejected', [
                    'status' => $response->status(),
                    'tags' => $this->tags,
                ]);
            }
        } catch (\Throwable $e) {
            // معزول الفشل: انقطاع الواجهة لا يكسر مسار النشر ولا يُفجِّر إعادة محاولة.
            Log::warning('[frontend-revalidate] failed', [
                'error' => $e->getMessage(),
                'tags' => $this->tags,
            ]);
        }
    }
}
