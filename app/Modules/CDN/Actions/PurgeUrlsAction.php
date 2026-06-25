<?php

declare(strict_types=1);

namespace App\Modules\CDN\Actions;

use App\Modules\CDN\Jobs\ProcessCdnPurgeBatch;
use App\Modules\CDN\Services\CloudflareClient;
use App\Modules\CDN\Support\CdnPurgeBuffer;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class PurgeUrlsAction
{
    public function handle(array $urls): JsonResponse
    {
        $client = new CloudflareClient;

        if (! $client->enabled()) {
            return ApiResponse::error(__('cdn.disabled'), [], 422);
        }

        $chunk = max(1, (int) config('cdn.api.purge_chunk', 30));

        // الحمولات الصغيرة (الحالة الغالبة): purge فوري — لا buffer مشترك،
        // لا سباق. الـ buffer يبقى للدفعات الكبيرة فقط.
        if (count($urls) <= $chunk) {
            return $client->purge($urls)
                ? ApiResponse::success(__('cdn.purge_done'), ['purged_urls' => count($urls)])
                : ApiResponse::error(__('cdn.purge_failed'), [], 422);
        }

        // دفعة كبيرة → تجميع ومعالجة دفعية عبر الطابور
        (new CdnPurgeBuffer)->add($urls);
        ProcessCdnPurgeBatch::dispatch();

        return ApiResponse::success(__('cdn.purge_queued'), [
            'queued_urls' => count($urls),
        ]);
    }
}
