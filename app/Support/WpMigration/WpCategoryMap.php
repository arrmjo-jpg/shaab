<?php

declare(strict_types=1);

namespace App\Support\WpMigration;

use App\Models\MigrationRun;

/**
 * مصدر الحقيقة الوحيد لخريطة التنسيب المحسومة لتشغيلة:
 *   term_taxonomy_id ⇒ {type, target, weight, term_id}
 *
 * يربط التنسيب المحفوظ (يخزّن wp_term_id) بحقائق التدقيق (تحمل term_taxonomy_id +
 * الأوزان) — لأن المنشورات في المصدر تُربط بالـ ttid لا بالـ term_id. يستعمله تعداد
 * اللقطة (SeedLedgerAction) ومُنسّق الاستيراد (ImportWpPostAction) معاً فلا ينحرفان.
 */
final class WpCategoryMap
{
    /**
     * @return array<int,array{type:string,target:int,weight:int,term_id:int}> مفتاحه term_taxonomy_id
     */
    public static function build(MigrationRun $run): array
    {
        $ttidByTerm = [];
        $weightByTerm = [];
        foreach (data_get($run->source_facts, 'categories.items', []) as $c) {
            $ttidByTerm[(int) $c['term_id']] = (int) $c['term_taxonomy_id'];
            $weightByTerm[(int) $c['term_id']] = (int) ($c['total_count'] ?? $c['count'] ?? 0);
        }

        $map = [];
        foreach ($run->categoryMaps as $m) {
            if (! $m->mode->isIncluded() || $m->target_category_id === null) {
                continue;
            }
            $ttid = $ttidByTerm[$m->wp_term_id] ?? null;
            if ($ttid === null) {
                continue;
            }
            $map[$ttid] = [
                'type' => $m->mode->value,
                'target' => $m->target_category_id,
                'weight' => $weightByTerm[$m->wp_term_id] ?? 0,
                'term_id' => $m->wp_term_id,
            ];
        }

        return $map;
    }

    /**
     * مُعرّفات term_taxonomy_id المُضمَّنة فقط (مفاتيح الخريطة) — مدخل تعداد اللقطة.
     *
     * @return array<int,int>
     */
    public static function includedTtids(MigrationRun $run): array
    {
        return array_keys(self::build($run));
    }
}
