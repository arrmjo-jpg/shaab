<?php

declare(strict_types=1);

namespace App\Actions\Admin\Media;

use App\Jobs\GenerateMediaAssetConversionsJob;
use App\Jobs\TranscodeVideoAssetJob;
use App\Models\MediaAsset;

/**
 * إعادة معالجة أصل مفرد (retry) — يعيد الحالة إلى queued ويُجدوِل الوظيفة
 * المناسبة: تحويل الصور أو ترميز الفيديو. لا ينطبق على الفيديو الخارجي.
 *
 * @return bool هل تمّت إعادة الجدولة؟
 */
class ReprocessMediaAssetAction
{
    public function handle(MediaAsset $asset): bool
    {
        if ($asset->isConvertibleImage()) {
            $asset->forceFill(['processing_status' => 'queued'])->save();
            GenerateMediaAssetConversionsJob::dispatch($asset->id);

            return true;
        }

        if ($asset->isUploadedVideo()) {
            $asset->forceFill(['processing_status' => 'queued'])->save();
            TranscodeVideoAssetJob::dispatch($asset->id);

            return true;
        }

        return false;
    }
}
