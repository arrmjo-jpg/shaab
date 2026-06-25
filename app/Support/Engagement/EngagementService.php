<?php

declare(strict_types=1);

namespace App\Support\Engagement;

use App\Enums\EngagementType;
use App\Models\Engagement;
use App\Models\EngagementCounter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * منطق التفاعل الموحّد للمنصّة — مصدر الحقيقة الوحيد لكل أنواع المحتوى.
 *
 * الأداء: العدّادات المُجمَّعة (engagement_counters) تُحدَّث بإعادة احتساب
 * التفاعلات (منخفضة الحجم) وزيادة المشاهدات مباشرة (عالية الحجم، بلا صفوف).
 */
final class EngagementService
{
    /** نافذة منع تكرار احتساب المشاهدة لكل (هدف+فاعل). */
    private const VIEW_DEDUP_MINUTES = 30;

    /**
     * تفاعل أحادي حصري (like/dislike). تبديل: نفس التفاعل يُلغيه؛ والمعاكس
     * يستبدل السابق. يُعيد حالة الفاعل + المقاييس.
     *
     * @return array<string,mixed>
     */
    public function react(Model $target, EngagementActor $actor, EngagementType $type): array
    {
        if (! $type->isReaction()) {
            throw new InvalidArgumentException('Only like/dislike are reactions.');
        }

        [$morph, $id] = $this->target($target);
        $key = $actor->key();

        DB::transaction(function () use ($morph, $id, $key, $actor, $type): void {
            $before = $this->currentReactionCounts($morph, $id);

            $hadSame = $this->actorQuery($morph, $id, $key)
                ->where('type', $type->value)
                ->exists();

            // أزِل أي تفاعل أحادي سابق لهذا الفاعل (يفرض «واحد فقط»).
            $this->actorQuery($morph, $id, $key)
                ->whereIn('type', [EngagementType::Like->value, EngagementType::Dislike->value])
                ->delete();

            if (! $hadSame) {
                $this->insert($morph, $id, $actor, $type);
            }

            $after = $this->syncCounters($morph, $id);
            $this->recordDailyReactionDelta($morph, $id, $before, $after);
        });

        return $this->stateFor($target, $actor);
    }

    /** يزيل أي تفاعل أحادي للفاعل. @return array<string,mixed> */
    public function removeReaction(Model $target, EngagementActor $actor): array
    {
        [$morph, $id] = $this->target($target);

        DB::transaction(function () use ($morph, $id, $actor): void {
            $before = $this->currentReactionCounts($morph, $id);
            $this->actorQuery($morph, $id, $actor->key())
                ->whereIn('type', [EngagementType::Like->value, EngagementType::Dislike->value])
                ->delete();
            $after = $this->syncCounters($morph, $id);
            $this->recordDailyReactionDelta($morph, $id, $before, $after);
        });

        return $this->stateFor($target, $actor);
    }

    /** تبديل الحفظ/المفضّلة (مستقلّ عن التفاعل الأحادي). @return array<string,mixed> */
    public function toggleFavorite(Model $target, EngagementActor $actor): array
    {
        [$morph, $id] = $this->target($target);
        $key = $actor->key();

        DB::transaction(function () use ($morph, $id, $key, $actor): void {
            $before = $this->currentReactionCounts($morph, $id);

            $existing = $this->actorQuery($morph, $id, $key)
                ->where('type', EngagementType::Favorite->value)
                ->first();

            if ($existing !== null) {
                $existing->delete();
            } else {
                $this->insert($morph, $id, $actor, EngagementType::Favorite);
            }

            $after = $this->syncCounters($morph, $id);
            $this->recordDailyReactionDelta($morph, $id, $before, $after);
        });

        return $this->stateFor($target, $actor);
    }

    /** احتساب مشاهدة بأمان (منع تكرار خلال نافذة لكل هدف+فاعل). */
    public function recordView(Model $target, EngagementActor $actor, string $channel = 'direct'): void
    {
        [$morph, $id] = $this->target($target);
        $this->recordViewFor($morph, $id, $actor, $channel);
    }

    /** نسخة بمفاتيح morph صريحة (للسياقات المُخزّنة مؤقتاً حيث لا نموذج مُحمَّل). */
    public function recordViewFor(string $engageableType, int $id, EngagementActor $actor, string $channel = 'direct'): void
    {
        // لا نحتسب مشاهدات الزواحف/البوتات (محرّكات بحث، معاينات روابط، زواحف AI).
        if ($actor->isBot) {
            return;
        }

        // منع تكرار ذرّي: Cache::add ينجح (SET NX على Redis) فقط إن لم يكن المفتاح
        // موجوداً، ويُعيد false وإلا — مطالبة واحدة فقط تنجح. يمنع الاحتساب المزدوج
        // تحت طلبات متزامنة لنفس الفاعل (سباق check-then-act الذي كان في has + put).
        $cacheKey = "engview:{$engageableType}:{$id}:{$actor->key()}";
        if (! Cache::add($cacheKey, true, now()->addMinutes(self::VIEW_DEDUP_MINUTES))) {
            return;
        }

        // مسار الإنتاج: تجميع في مخزن مؤقّت (Redis) ثم تفريغ مجدوَل — يزيل تنازع
        // الصفّ الساخن تحت الانتشار الفيروسي، ويكتب التجميع اليوميّ (مع تفصيل القناة)
        // عند التفريغ دفعةً (خارج المسار الساخن). عند التعطيل/عدم الدعم (مخزن بلا أقفال)
        // نزيد العدّاد مباشرةً فقط — تيليمتري المشاهدات اليوميّ يتبع المخزن المؤقّت.
        if (config('performance.view_buffer.enabled') && ViewBuffer::supported()) {
            ViewBuffer::add($engageableType, $id, $channel);

            return;
        }

        $this->incrementViewCounter($engageableType, $id);
    }

    /** زيادة عدّاد المشاهدات مباشرةً (المسار المتزامن): الصفّ الموجود وإلا يُنشأ. */
    private function incrementViewCounter(string $engageableType, int $id): void
    {
        $affected = EngagementCounter::query()
            ->where('engageable_type', $engageableType)
            ->where('engageable_id', $id)
            ->increment('views');

        if ($affected === 0) {
            EngagementCounter::query()->firstOrCreate(
                ['engageable_type' => $engageableType, 'engageable_id' => $id],
            )->increment('views');
        }
    }

    /** @return array{views:int,likes:int,dislikes:int,favorites:int} */
    public function metrics(Model $target): array
    {
        [$morph, $id] = $this->target($target);
        $c = EngagementCounter::query()
            ->where('engageable_type', $morph)
            ->where('engageable_id', $id)
            ->first();

        return [
            'views' => (int) ($c->views ?? 0),
            'likes' => (int) ($c->likes ?? 0),
            'dislikes' => (int) ($c->dislikes ?? 0),
            'favorites' => (int) ($c->favorites ?? 0),
        ];
    }

    /** حالة الفاعل + المقاييس (عقد عام). @return array<string,mixed> */
    public function stateFor(Model $target, EngagementActor $actor): array
    {
        [$morph, $id] = $this->target($target);
        $key = $actor->key();

        $reaction = $this->actorQuery($morph, $id, $key)
            ->whereIn('type', [EngagementType::Like->value, EngagementType::Dislike->value])
            ->value('type');

        $favorited = $this->actorQuery($morph, $id, $key)
            ->where('type', EngagementType::Favorite->value)
            ->exists();

        return [
            // قد يُعيد الـ cast نسخة enum — نُطبّع إلى نصّ خام للعقد العام.
            'reaction' => $reaction instanceof EngagementType ? $reaction->value : $reaction,
            'favorited' => $favorited,
            'metrics' => $this->metrics($target),
        ];
    }

    /** @return array{0:string,1:int} */
    private function target(Model $target): array
    {
        return [$target->getMorphClass(), (int) $target->getKey()];
    }

    private function actorQuery(string $morph, int $id, string $actorKey): Builder
    {
        return Engagement::query()
            ->where('engageable_type', $morph)
            ->where('engageable_id', $id)
            ->where('actor_key', $actorKey);
    }

    private function insert(string $morph, int $id, EngagementActor $actor, EngagementType $type): void
    {
        Engagement::query()->create([
            'engageable_type' => $morph,
            'engageable_id' => $id,
            'user_id' => $actor->userId,
            'fingerprint' => $actor->fingerprint,
            'actor_key' => $actor->key(),
            'type' => $type->value,
        ]);
    }

    /**
     * يعيد احتساب عدّادات التفاعل (يحفظ المشاهدات كما هي). استعلام تجميعي واحد
     * (GROUP BY type) مدعوم بفهرس engagements_target_type_idx بدل ثلاثة COUNT —
     * يقلّص تضخّم الكتابة على أكثر الجداول سخونة. النتيجة مطابقة تماماً.
     */
    /** @return array{likes:int,dislikes:int,favorites:int} */
    private function syncCounters(string $morph, int $id): array
    {
        // builder خام (لا hydration) كي تبقى مفاتيح type نصوصاً لا enum.
        $counts = DB::table('engagements')
            ->where('engageable_type', $morph)
            ->where('engageable_id', $id)
            ->whereIn('type', [
                EngagementType::Like->value,
                EngagementType::Dislike->value,
                EngagementType::Favorite->value,
            ])
            ->groupBy('type')
            ->selectRaw('type, COUNT(*) as aggregate')
            ->pluck('aggregate', 'type');

        $values = [
            'likes' => (int) ($counts[EngagementType::Like->value] ?? 0),
            'dislikes' => (int) ($counts[EngagementType::Dislike->value] ?? 0),
            'favorites' => (int) ($counts[EngagementType::Favorite->value] ?? 0),
        ];

        EngagementCounter::query()->updateOrCreate(
            ['engageable_type' => $morph, 'engageable_id' => $id],
            $values,
        );

        return $values;
    }

    /**
     * عددات التفاعل الحالية من العدّاد (قبل التعديل) — لحساب دلتا اليوم.
     *
     * @return array{likes:int,dislikes:int,favorites:int}
     */
    private function currentReactionCounts(string $morph, int $id): array
    {
        $c = EngagementCounter::query()
            ->where('engageable_type', $morph)
            ->where('engageable_id', $id)
            ->first(['likes', 'dislikes', 'favorites']);

        return [
            'likes' => (int) ($c->likes ?? 0),
            'dislikes' => (int) ($c->dislikes ?? 0),
            'favorites' => (int) ($c->favorites ?? 0),
        ];
    }

    /**
     * يسجّل دلتا التفاعل الصافية لليوم في التجميع اليوميّ (تيليمتري عبر الزمن). تُحسب
     * من فرق العددات قبل/بعد — صحيحة مهما كان الانتقال (إضافة/تبديل/إلغاء).
     *
     * @param  array{likes:int,dislikes:int,favorites:int}  $before
     * @param  array{likes:int,dislikes:int,favorites:int}  $after
     */
    private function recordDailyReactionDelta(string $morph, int $id, array $before, array $after): void
    {
        DailyEngagementRollup::addReactionDeltas(
            $morph,
            $id,
            $after['likes'] - $before['likes'],
            $after['dislikes'] - $before['dislikes'],
            $after['favorites'] - $before['favorites'],
        );
    }
}
