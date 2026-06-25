<?php

declare(strict_types=1);

use App\Jobs\GenerateMediaAssetConversionsJob;
use App\Jobs\TranscodeVideoAssetJob;
use Illuminate\Support\Facades\Queue;

/**
 * يضمن توجيه مهام الوسائط الثقيلة إلى طابور media فعلياً (عبر خصائص onQueue في
 * الباني) — بعد إزالة viaQueue() الميّت الذي لم يكن يُقرأ للمهام. عامل طابور
 * media المنفصل يعتمد على هذا التوجيه.
 */
it('routes the video transcode job to the media queue', function (): void {
    Queue::fake();

    TranscodeVideoAssetJob::dispatch(123);

    Queue::assertPushedOn('media', TranscodeVideoAssetJob::class);
});

it('routes the image conversions job to the media queue', function (): void {
    Queue::fake();

    GenerateMediaAssetConversionsJob::dispatch(456);

    Queue::assertPushedOn('media', GenerateMediaAssetConversionsJob::class);
});
