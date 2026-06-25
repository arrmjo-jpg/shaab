<?php

declare(strict_types=1);

namespace App\Support\Engagement;

use App\Enums\TrafficChannel;
use Illuminate\Support\Facades\DB;

/**
 * كاتب التجميع اليوميّ للتفاعل (content_daily_stats) — تيليمتري إلى-الأمام فقط.
 *
 * يُستدعى من نقاط الكتابة القائمة فقط (بلا بنية جديدة): المشاهدات من ViewBuffer::flush
 * (مُجمَّعة، دفعة دورية) أو المسار المتزامن، والتفاعلات من EngagementService (منخفض
 * الحجم). الزيادة ذرّية على مستوى القاعدة: نضمن صفّ اليوم (insertOrIgnore عبر فرادة
 * (نوع،مُعرّف،يوم)) ثم نطبّق «col = col + delta» — آمن تحت التزامن وعبر MySQL/SQLite.
 */
final class DailyEngagementRollup
{
    private const TABLE = 'content_daily_stats';

    /** أعمدة قابلة للزيادة (قائمة بيضاء — دفاع ضدّ الحقن في التعبير الخام). */
    private const COLUMNS = [
        'views', 'likes', 'dislikes', 'favorites',
        'views_direct', 'views_internal', 'views_search', 'views_social', 'views_referral',
    ];

    /**
     * يضيف مشاهدات اليوم (إجمالي + تفصيل حسب القناة) للهدف.
     *
     * @param  array<string,int>  $channelDeltas  مفاتيحها قيم TrafficChannel
     */
    public static function addViews(string $type, int $id, int $totalViews, array $channelDeltas = []): void
    {
        if ($totalViews <= 0) {
            return;
        }

        $inc = ['views' => $totalViews];
        foreach ($channelDeltas as $channel => $delta) {
            $delta = (int) $delta;
            if ($delta === 0) {
                continue;
            }
            $col = (TrafficChannel::tryFrom((string) $channel) ?? TrafficChannel::Direct)->column();
            $inc[$col] = ($inc[$col] ?? 0) + $delta;
        }

        self::bump($type, $id, $inc);
    }

    /** يضيف دلتا التفاعل الصافية لليوم (قد تكون سالبة عند التبديل/الإلغاء). */
    public static function addReactionDeltas(string $type, int $id, int $likes, int $dislikes, int $favorites): void
    {
        $inc = array_filter(
            ['likes' => $likes, 'dislikes' => $dislikes, 'favorites' => $favorites],
            static fn (int $v): bool => $v !== 0,
        );
        if ($inc === []) {
            return;
        }

        self::bump($type, $id, $inc);
    }

    /**
     * زيادة ذرّية على صفّ اليوم.
     *
     * @param  array<string,int>  $increments
     */
    private static function bump(string $type, int $id, array $increments): void
    {
        $now = now();
        $keys = ['engageable_type' => $type, 'engageable_id' => $id, 'day' => $now->toDateString()];

        // ضمان وجود الصفّ — ذرّيّ عبر فرادة (نوع،مُعرّف،يوم)؛ يتجاهل التكرار.
        DB::table(self::TABLE)->insertOrIgnore(array_merge($keys, [
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        $set = ['updated_at' => $now];
        foreach ($increments as $col => $delta) {
            if (! in_array($col, self::COLUMNS, true)) {
                continue;
            }
            $set[$col] = DB::raw($col.' + '.(int) $delta);
        }

        if (count($set) > 1) {
            DB::table(self::TABLE)->where($keys)->update($set);
        }
    }
}
