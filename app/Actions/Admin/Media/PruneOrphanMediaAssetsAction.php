<?php

declare(strict_types=1);

namespace App\Actions\Admin\Media;

use App\Models\MediaAsset;
use App\Support\Media\MediaFileCleaner;
use App\Support\Media\MediaUsage;

/**
 * يحذف أصول المكتبة المرحّلة غير المُسنَدة لأي مقال والأقدم من TTL
 * (staged-but-abandoned uploads من نموذج attach-on-save).
 *
 * - يقتصر على أصول المكتبة (path يبدأ بـ assets/) — لا يمسّ أصول الإعدادات
 *   (branding/...).
 * - يحذف الملفات (الأصل + المشتقّات + مجلد الأصل) ثم النموذج عبر Eloquent
 *   delete() كي يبقى التدقيق (Rule 2 — لا تجاوز أحداث Eloquent للنماذج المُدقَّقة).
 *
 * لا HTTP — يُستدعى من الأمر media:prune-orphans (مُجدوَل).
 */
class PruneOrphanMediaAssetsAction
{
    public function handle(?int $ttlHours = null): int
    {
        $ttlHours ??= (int) config('performance.media.orphan_ttl_hours', 48);
        $threshold = now()->subHours(max(0, $ttlHours));

        $deleted = 0;

        // «مُستخدَم» يُعرَّف في مكان واحد (MediaUsage) يغطّي كلّ المستهلكين (مقالات/
        // تغطيات/og/ريلز/فيديو/أغلفة تصنيفات وقوائم/بثّ وتصنيفاته/أعداد ونُسخها)،
        // ويُضمِّن المالك المحذوف ناعماً (withTrashed حيثما يُدعَم) فلا يُنظَّف أصلٌ
        // تستعيده محتوياتٌ من السلّة. لا قوائم علاقات مبعثرة هنا.
        $query = MediaAsset::query()
            ->library()
            ->where('created_at', '<', $threshold);

        MediaUsage::constrainUnused($query);

        $query->chunkById(100, function ($assets) use (&$deleted): void {
            foreach ($assets as $asset) {
                MediaFileCleaner::purge($asset);
                $asset->delete(); // Eloquent → يُسجَّل في activity_log (deleted)
                $deleted++;
            }
        });

        return $deleted;
    }
}
