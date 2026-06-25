<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Support\Audit\MediaUsageAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * يزامن صفوف article_media لمالكٍ (مقال أو تحديث تغطية حيّة) من حمولة إسناد
 * مُتحقَّق منها (نموذج client-stage → attach-on-save المقفول).
 *
 * - يربط الأصول الجديدة، يفصل المحذوفة، يحدّث المجموعة/الموضع المتغيّر.
 * - أصل واحد لكل مالك (مفتاح المزامنة = asset_id؛ مجموعة واحدة لكل أصل).
 * - يُنتج تدقيق استخدام يدوي (attach/detach/cover-replace) بلا ضجيج إعادة ترتيب.
 *
 * المالك أيّ نموذج يملك علاقة mediaAssets() (Article | ArticleLiveUpdate).
 * لا يحذف الأصل من المكتبة المركزية عند الفصل (مشترك).
 */
final class MediaAttachmentSyncer
{
    /**
     * @param  array<int,array{asset_id:int|string,collection:string,position?:int|string}>  $items
     */
    public static function sync(Model $owner, array $items): void
    {
        /** @var BelongsToMany $relation */
        $relation = $owner->mediaAssets();

        // الحالة الحالية: asset_id => [collection, position]
        $current = $relation->get(['media_assets.id'])
            ->mapWithKeys(fn ($a): array => [(int) $a->id => [
                'collection' => (string) $a->pivot->collection,
                'position' => (int) $a->pivot->position,
            ]])
            ->all();

        // الحالة المرغوبة من الحمولة (الاستوديو يدير cover/gallery/video)
        $desired = [];
        foreach ($items as $item) {
            $assetId = (int) $item['asset_id'];
            $desired[$assetId] = [
                'collection' => (string) $item['collection'],
                'position' => (int) ($item['position'] ?? 0),
            ];
        }

        // الصور داخل النصّ (inline) يديرها المحرّر لا الاستوديو — نحفظها
        // كي لا يفصلها الحفظ من النموذج.
        foreach ($current as $id => $row) {
            if ($row['collection'] === 'inline' && ! isset($desired[$id])) {
                $desired[$id] = $row;
            }
        }

        // sync على الـ pivot (ليس نموذجاً مُدقَّقاً — التدقيق يدوي أدناه)
        $owner->mediaAssets()->sync($desired);

        // ── تدقيق الاستخدام (يدوي، حسب النموذج الهجين) ──
        $oldIds = array_keys($current);
        $newIds = array_keys($desired);

        MediaUsageAudit::attached($owner, array_values(array_diff($newIds, $oldIds)));
        MediaUsageAudit::detached($owner, array_values(array_diff($oldIds, $newIds)));

        // استبدال الغلاف: تغيّر الأصل المُعيَّن لخانة cover المفردة
        $oldCover = false;
        foreach ($current as $id => $row) {
            if ($row['collection'] === 'cover') {
                $oldCover = $id;
                break;
            }
        }
        $newCover = null;
        foreach ($desired as $id => $d) {
            if ($d['collection'] === 'cover') {
                $newCover = $id;
                break;
            }
        }
        if ($oldCover !== false && $newCover !== null && (int) $oldCover !== $newCover) {
            MediaUsageAudit::replaced($owner, 'cover', (int) $oldCover, $newCover);
        }
    }
}
