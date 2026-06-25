<?php

declare(strict_types=1);

namespace App\Actions\Admin\Media;

use App\Jobs\GenerateMediaAssetConversionsJob;
use App\Models\MediaAsset;

/**
 * إعادة توليد مشتقّات الصور لكل أصول المكتبة (retroactive) — تُستخدَم بعد
 * تغيير إعدادات العلامة المائية كي تنعكس على الأصول الموجودة لا الجديدة فقط.
 *
 * يضع كل أصل صورة في حالة queued ويُجدوِل وظيفة التحويل (queue=media).
 * المصدر يبقى نظيفاً؛ تُعاد كتابة thumb/medium/watermarked فقط.
 */
class RegenerateMediaDerivativesAction
{
    public function handle(): int
    {
        $count = 0;

        MediaAsset::query()
            ->library()
            ->whereIn('mime_type', ['image/jpeg', 'image/png', 'image/webp'])
            ->chunkById(100, function ($assets) use (&$count): void {
                foreach ($assets as $asset) {
                    $asset->forceFill(['processing_status' => 'queued'])->save();
                    GenerateMediaAssetConversionsJob::dispatch($asset->id);
                    $count++;
                }
            });

        return $count;
    }
}
