<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * فهرسة أعلام الأخبار التي تُفلتَر بها لوحة الإدارة: is_breaking / is_header /
 * is_editor_pick. (is_featured مُغطّى أصلاً بالمركّب articles_featured_pub_idx
 * الذي يقوده، فيُتخطّى تلقائياً.)
 *
 * لماذا فهرس على عمود boolean؟ شائع الظنّ أن «cardinality=2 عديم الفائدة» — وهو
 * صحيح لمطابقة القيمة الشائعة (false). لكن الفلاتر هنا تطابق القيمة النادرة
 * (=true: بضعة أخبار من ~79k)، فالفهرس انتقائيّ جداً ويحوّل المسح الكامل (~200ms
 * بارد) إلى بحث نقطي (~1ms) — تماماً كما يفعل is_featured عبر مركّبه.
 *
 * (تصحيح لقرار optimize_articles_indexes الذي أسقط هذه الفهارس بناءً على ذلك
 * الظنّ؛ هذا الـmigration يتلوه فيُعيدها — idempotent، يُضيف فقط ما هو غائب.)
 *
 * فهرسة online آمنة إنتاجاً: ALGORITHM=INPLACE, LOCK=NONE → لا قفل جدول، تستمر
 * القراءة/الكتابة أثناء البناء. تخصيص بنية فقط — لا تعديل بيانات.
 */
return new class extends Migration
{
    /** @var array<int,string> أعلام تُفلتَر بـ =true وتحتاج فهرساً قائداً. */
    private const FLAGS = ['is_breaking', 'is_header', 'is_editor_pick'];

    public function up(): void
    {
        $mysql = DB::connection()->getDriverName() === 'mysql';

        foreach (self::FLAGS as $col) {
            if ($this->columnLeadsIndex($col)) {
                continue; // مفهرس أصلاً (مفرد أو يقود مركّباً) — لا تكرّر.
            }
            $name = "articles_{$col}_index";

            if ($mysql) {
                // online: لا قفل، لا إعادة بناء جدول. ملاحظة صياغة CREATE INDEX:
                // ALGORITHM وLOCK مفصولان بمسافة لا بفاصلة (الفاصلة خاصّة بـ ALTER TABLE).
                DB::statement("CREATE INDEX {$name} ON articles ({$col}) ALGORITHM=INPLACE LOCK=NONE");
            } else {
                Schema::table('articles', fn ($t) => $t->index($col, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::FLAGS as $col) {
            $name = "articles_{$col}_index";
            if ($this->indexExists($name)) {
                Schema::table('articles', fn ($t) => $t->dropIndex($name));
            }
        }
    }

    /** هل العمود هو العمود القائد لأي فهرس؟ (القيادة شرط خدمة WHERE col=?). */
    private function columnLeadsIndex(string $column): bool
    {
        return collect(Schema::getIndexes('articles'))
            ->contains(fn (array $i): bool => ($i['columns'][0] ?? null) === $column);
    }

    private function indexExists(string $name): bool
    {
        return collect(Schema::getIndexes('articles'))
            ->contains(fn (array $i): bool => $i['name'] === $name);
    }
};
