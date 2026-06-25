<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * تدقيق يدوي لأحداث استخدام الوسائط على مستوى المالك (مقال أو تحديث تغطية حيّة).
 *
 * صفوف article_media ليست نموذجاً مُدقَّقاً تلقائياً، فنُسجّل الأحداث التحريرية
 * المهمّة يدوياً: إسناد / فصل / استبدال (غلاف/فيديو).
 *
 * لا تُسجَّل إعادة الترتيب (position) إطلاقاً — تفادي ضجيج التدقيق.
 */
final class MediaUsageAudit
{
    /** @param array<int,int> $assetIds */
    public static function attached(Model $owner, array $assetIds): void
    {
        if ($assetIds === []) {
            return;
        }

        activity('media')
            ->performedOn($owner)
            ->event('media_attached')
            ->withProperties(['owner_id' => $owner->getKey(), 'asset_ids' => array_values($assetIds)])
            ->log(__('audit.media.attached', ['count' => count($assetIds)]));
    }

    /** @param array<int,int> $assetIds */
    public static function detached(Model $owner, array $assetIds): void
    {
        if ($assetIds === []) {
            return;
        }

        activity('media')
            ->performedOn($owner)
            ->event('media_detached')
            ->withProperties(['owner_id' => $owner->getKey(), 'asset_ids' => array_values($assetIds)])
            ->log(__('audit.media.detached', ['count' => count($assetIds)]));
    }

    /** استبدال أصل في خانة مفردة (cover/video). */
    public static function replaced(Model $owner, string $slot, int $oldAssetId, int $newAssetId): void
    {
        activity('media')
            ->performedOn($owner)
            ->event('media_replaced')
            ->withProperties([
                'owner_id' => $owner->getKey(),
                'slot' => $slot,
                'old_asset_id' => $oldAssetId,
                'new_asset_id' => $newAssetId,
            ])
            ->log(__('audit.media.replaced', ['slot' => $slot]));
    }
}
