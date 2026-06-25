<?php

declare(strict_types=1);

namespace App\Modules\CDN\Jobs;

use App\Modules\CDN\Services\CloudflareClient;
use App\Modules\CDN\Support\CdnPurgeBuffer;
use App\Modules\CDN\Support\CdnQueues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * يفرّغ مخزّن الـ purge المؤقت على دفعات إلى Cloudflare.
 */
class ProcessCdnPurgeBatch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    /** سقف زمني صلب — يمنع عامل الطابور من التعلّق على نداء Cloudflare. */
    public int $timeout = 30;

    public function __construct()
    {
        $this->onQueue(CdnQueues::PURGE);
    }

    public function handle(CdnPurgeBuffer $buffer, CloudflareClient $client): void
    {
        // قفل موزّع حول الإفراغ — يمنع تداخل وظيفتين متزامنتين
        Cache::lock('cdn:purge:flush', 30)->block(5, function () use ($buffer, $client): void {
            foreach ($buffer->flushChunks() as $chunk) {
                $client->purge($chunk);
            }
        });
    }
}
