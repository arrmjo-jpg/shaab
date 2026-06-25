<?php

declare(strict_types=1);

namespace App\Support\Advertising;

use App\Enums\AdEventType;
use App\Models\AdCounter;
use App\Models\AdPlacement;
use App\Support\Engagement\EngagementActor;
use Illuminate\Support\Facades\Cache;

/**
 * نقطة تسجيل أحداث الإعلان — تفرض حمايات الاحتيال على مستوى الخدمة (تصفية البوتات +
 * إزالة التكرار/مقاومة إعادة التشغيل). حدّ المعدّل وفحص الرمز يُطبَّقان على مستوى المسار
 * (Batch 5)؛ هنا التصفية والإزالة والتجميع.
 *
 * دلالات الانطباع (B — مُزال التكرار): انطباع واحد لكل (إسناد، فاعل) ضمن نافذة الدلو
 * (افتراضياً 30ث). إعادة العرض/التحديث ضمن الدلو تُحتسب مرّة؛ التعرّض المتكرّر في دلو
 * لاحق يُحتسب ثانيةً (تردّد عادل + مقاومة إعادة تشغيل). النقرات تتبع نفس القاعدة.
 */
final class AdTracker
{
    /**
     * يسجّل حدثاً. يُعيد true إن احتُسب، false إن رُفض (بوت/مكرّر/إعادة تشغيل).
     */
    public static function record(
        AdEventType $type,
        int $placementId,
        EngagementActor $actor,
        string $channel = 'direct',
        ?int $bucket = null,
        ?string $ipKey = null,
    ): bool {
        if ($actor->isBot) {
            return false; // تصفية البوتات
        }

        $bucket ??= AdBucket::current();

        // إزالة تكرار ذرّية لكل (نوع، إسناد، هوية، دلو) — SET NX. تمنع التضخيم/إعادة التشغيل.
        if (! Cache::add(self::dedupKey($type, $placementId, $actor, $bucket, $ipKey), true, now()->addSeconds(AdBucket::window() * 2))) {
            return false;
        }

        if (config('advertising.tracking.buffer_enabled', true) && AdEventBuffer::supported()) {
            AdEventBuffer::add($type->value, $placementId, $channel);
        } else {
            self::direct($type, $placementId, $channel);
        }

        return true;
    }

    /**
     * مفتاح منع التكرار (V1). النقر مع تفعيل strict_click_dedup ووجود مفتاح IP ⇒ ارتكاز
     * على الـ IP (نقرة واحدة لكل IP/إسناد/دلو، مقاوم لتدوير X-Client-Id). غير ذلك (وكلّ
     * الانطباعات) ⇒ ارتكاز على الفاعل — السلوك الافتراضي الآمن (لا يطوي مستخدمي IP مشترك).
     */
    private static function dedupKey(AdEventType $type, int $placementId, EngagementActor $actor, int $bucket, ?string $ipKey): string
    {
        $strictClick = $type === AdEventType::Click
            && $ipKey !== null
            && (bool) config('advertising.tracking.strict_click_dedup', false);

        $identity = $strictClick ? 'ip:'.$ipKey : $actor->key();

        return 'adtrk:'.$type->value.':'.$placementId.':'.$identity.':'.$bucket;
    }

    /** مسار متزامن احتياطيّ (مخزن بلا أقفال) — يضمن الصفّ ثم يزيد + تجميع يوميّ. */
    private static function direct(AdEventType $type, int $placementId, string $channel): void
    {
        $col = $type === AdEventType::Click ? 'clicks' : 'impressions';
        AdCounter::query()->firstOrCreate(['ad_placement_id' => $placementId]);
        AdCounter::query()->where('ad_placement_id', $placementId)->increment($col);

        $p = AdPlacement::query()
            ->with('creative:id,ad_campaign_id')
            ->find($placementId, ['id', 'ad_creative_id', 'ad_zone_id']);

        $impressions = $type === AdEventType::Impression ? 1 : 0;
        $clicks = $type === AdEventType::Click ? 1 : 0;

        AdStatsRollup::add(
            $placementId,
            $p?->ad_zone_id !== null ? (int) $p->ad_zone_id : null,
            $p?->ad_creative_id !== null ? (int) $p->ad_creative_id : null,
            $p?->creative?->ad_campaign_id !== null ? (int) $p->creative->ad_campaign_id : null,
            $impressions,
            $clicks,
            $impressions > 0 ? [$channel => 1] : [],
        );
    }
}
