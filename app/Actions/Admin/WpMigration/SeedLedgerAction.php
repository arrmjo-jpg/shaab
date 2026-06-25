<?php

declare(strict_types=1);

namespace App\Actions\Admin\WpMigration;

use App\Enums\MigrationItemStatus;
use App\Models\MigrationItem;
use App\Models\MigrationRun;
use App\Support\WpMigration\WpCategoryMap;
use App\Support\WpMigration\WpSourceConnection;
use Illuminate\Database\Connection;

/**
 * لقطة الدفتر (قاعدة #1): يُعدّد مرّة واحدة عند بدء التنفيذ كل منشور مؤهَّل
 * (publish + ضمن تصنيف مُضمَّن) من المصدر، ويبذره في دفتر العناصر بحالة pending
 * عبر إدراج idempotent (insertOrIgnore على المفتاح الفريد run_id+wp_post_id).
 * بعد البذر يعمل التنفيذ من اللقطة لا بإعادة استعلام المصدر.
 *
 * التعداد بالمفتاح التتابعي (keyset على ID تصاعدياً) بدفعات محدودة — لا حلقات
 * عملاقة على 84k+ منشور (#2). الاتصال مُحقَن (قابل للاختبار بـ sqlite كبقية القرّاء).
 */
class SeedLedgerAction
{
    public function __construct(
        private readonly Connection $db,
        private readonly bool $incremental = false,
    ) {}

    public static function for(MigrationRun $run, bool $incremental = false): self
    {
        return new self(WpSourceConnection::connection($run), $incremental);
    }

    /** يعيد إجمالي عناصر الدفتر بعد البذر. */
    public function handle(MigrationRun $run): int
    {
        $ttids = WpCategoryMap::includedTtids($run);
        if ($ttids === []) {
            $run->forceFill(['total_items' => 0])->save();

            return 0;
        }

        $chunk = max(1, (int) config('wp-migration.chunk', 200));
        $now = now();

        // الوضع التزايديّ (opt-in): يبدأ المفتاح التتابعيّ من أعلى wp_post_id جرى
        // استيراده فعلاً (status=done/partial ⇒ المقال أُنشئ)، لا مجرّد ما بُذِر — كي لا
        // تَحجب لقطةٌ سابقة بُذِرت ولم تُستورَد (عالقة) الأخبارَ الجديدة. هوية المصدر =
        // wp_post_id حصراً. الوضع الكامل (الافتراضيّ) يبدأ من 0 — سلوك غير متغيّر تماماً.
        $lastId = $this->incremental
            ? (int) MigrationItem::query()
                ->whereIn('status', [MigrationItemStatus::Done->value, MigrationItemStatus::Partial->value])
                ->max('wp_post_id')
            : 0;

        do {
            $ids = $this->db->table('term_relationships as tr')
                ->join('posts as p', 'p.ID', '=', 'tr.object_id')
                ->whereIn('tr.term_taxonomy_id', $ttids)
                ->where('p.post_type', 'post')
                ->where('p.post_status', 'publish')
                ->where('p.ID', '>', $lastId)
                ->orderBy('p.ID')
                ->distinct()
                ->limit($chunk)
                ->pluck('p.ID')
                ->map(fn ($x): int => (int) $x)
                ->all();

            if ($ids === []) {
                break;
            }

            MigrationItem::insertOrIgnore(array_map(fn (int $id): array => [
                'run_id' => $run->id,
                'wp_post_id' => $id,
                'status' => MigrationItemStatus::Pending->value,
                'attempts' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ], $ids));

            $lastId = (int) end($ids);
        } while (count($ids) === $chunk);

        $total = MigrationItem::query()->where('run_id', $run->id)->count();
        $run->forceFill(['total_items' => $total])->save();

        return $total;
    }
}
