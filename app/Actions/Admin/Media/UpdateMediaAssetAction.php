<?php

declare(strict_types=1);

namespace App\Actions\Admin\Media;

use App\Models\MediaAsset;

/**
 * تحديث البيانات الوصفية التحريرية (alt/caption/credit/source) لأصل موجود
 * دون إعادة رفع الملف. عبر Eloquent → يُدقَّق تلقائياً (alt/caption/credit/source
 * ضمن auditAttributes للنموذج).
 */
class UpdateMediaAssetAction
{
    public function handle(MediaAsset $asset, array $validated): MediaAsset
    {
        foreach (['alt', 'caption', 'credit', 'source'] as $field) {
            if (array_key_exists($field, $validated)) {
                $asset->{$field} = $validated[$field];
            }
        }

        $asset->save();

        return $asset->fresh();
    }
}
