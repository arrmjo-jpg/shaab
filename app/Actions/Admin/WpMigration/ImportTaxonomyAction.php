<?php

declare(strict_types=1);

namespace App\Actions\Admin\WpMigration;

use App\Enums\ArticleType;
use App\Enums\CategoryScope;
use App\Enums\CategoryStatus;
use App\Enums\WpCategoryDisposition;
use App\Models\Category;
use App\Models\MigrationCategoryMap;
use App\Models\MigrationRun;
use App\Support\Content\SlugGenerator;
use App\Support\Responses\ApiResponse;
use App\Support\WpMigration\MigrationSequence;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * استيراد التصنيفات (Phase 9) — مرحلة تخطيط فقط: يُنشئ تصنيفات AlphaCMS من صفوف
 * التنسيب ذات disposition=create قبل التنفيذ. لا يقرأ مصدر ووردبريس (يعمل من لقطة
 * الصفوف المحفوظة: الاسم/المعرّف/الأب). الإبقاء الحتميّ:
 *
 *  - الترتيب الطوبولوجيّ: الأب قبل الابن (عبر العودية + memo).
 *  - الهرمية: يُعشَّش الابن فقط تحت أب «create» بنفس النوع وضمن MAX_DEPTH — وإلا جذر.
 *  - الاسم: اسم تصنيف المصدر. النطاق: من النوع (news→news، articles→opinion). اللغة: لغة المصدر.
 *  - الـ slug: slug المصدر المفكوك إن صحّ، وإلا مولّد من الاسم؛ عند الاصطلاح: «-wp-{term_id}».
 *  - idempotent: يُعيد استخدام created_category_id (ويستعيده إن حُذف) فلا تكرار عند إعادة التشغيل.
 *  - الحالة: نشِط فوراً. created_category_id + target_category_id = التصنيف المُنشأ.
 */
class ImportTaxonomyAction
{
    public function handle(MigrationRun $run): JsonResponse
    {
        $locale = (string) (data_get($run->source_facts, 'site.language') ?: 'ar');

        /** @var Collection<int,MigrationCategoryMap> $createRows */
        $createRows = $run->categoryMaps()
            ->where('disposition', WpCategoryDisposition::Create->value)
            ->get()
            ->keyBy('wp_term_id');

        // كل صفّ create يحتاج نوع محتوى محسوم (news/articles) قبل أي إنشاء.
        foreach ($createRows as $m) {
            if (! $m->mode->isIncluded()) {
                return ApiResponse::error(__('wp_migration.map.type_required'), [], 422);
            }
        }

        // فحص تصادم المعرّفات المحفوظة (#2): الصفوف التي ستُدرَج حديثاً بمعرّف = wp_term_id.
        // إن كان أحد المعرّفات مأخوذاً بتصنيف قائم (ليس هدفنا المُنشأ سابقاً) ⇒ تعارض صريح
        // (لا إعادة توليد) — يُبلَّغ قبل أي إنشاء فلا تراجع جزئيّ.
        $newTermIds = $createRows
            ->filter(fn (MigrationCategoryMap $m): bool => $m->created_category_id === null
                || Category::withTrashed()->whereKey($m->created_category_id)->doesntExist())
            ->keys()
            ->map(fn ($k): int => (int) $k)
            ->all();

        if ($newTermIds !== []) {
            $conflicts = Category::withTrashed()->whereIn('id', $newTermIds)->pluck('id')->all();
            if ($conflicts !== []) {
                return ApiResponse::error(
                    __('wp_migration.taxonomy.id_conflict'),
                    ['ids' => array_values(array_map('intval', $conflicts))],
                    422,
                );
            }
        }

        $created = 0;
        $reused = 0;
        $catMemo = [];    // wp_term_id => category id
        $depthMemo = [];  // wp_term_id => depth (1 = جذر)
        $visiting = [];   // حارس دورة (شجرة مصدر تالفة)

        $resolve = function (int $wpTermId) use (&$resolve, &$createRows, &$created, &$reused, &$catMemo, &$depthMemo, &$visiting, $locale): array {
            if (isset($catMemo[$wpTermId])) {
                return ['id' => $catMemo[$wpTermId], 'depth' => $depthMemo[$wpTermId]];
            }

            /** @var MigrationCategoryMap $m */
            $m = $createRows[$wpTermId];

            // الأب: يُعشَّش فقط تحت أب «create» بنفس النوع وضمن حدّ العمق — وإلا جذر.
            $parentId = null;
            $depth = 1;
            $parentWp = (int) ($m->wp_parent_id ?? 0);
            if ($parentWp > 0
                && isset($createRows[$parentWp])
                && $createRows[$parentWp]->mode === $m->mode
                && ! isset($visiting[$parentWp])) {
                $visiting[$wpTermId] = true;
                $parent = $resolve($parentWp);
                unset($visiting[$wpTermId]);
                if ($parent['depth'] < Category::MAX_DEPTH) {
                    $parentId = $parent['id'];
                    $depth = $parent['depth'] + 1;
                }
            }

            // idempotent: أعِد استخدام التصنيف المُنشأ سابقاً (واستعِده إن حُذف منطقياً).
            if ($m->created_category_id !== null) {
                $existing = Category::withTrashed()->find($m->created_category_id);
                if ($existing !== null) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $m->forceFill(['target_category_id' => $existing->id])->save();
                    $catMemo[$wpTermId] = $existing->id;
                    $depthMemo[$wpTermId] = $depth;
                    $reused++;

                    return ['id' => $existing->id, 'depth' => $depth];
                }
            }

            $scope = $m->mode->articleType() === ArticleType::News
                ? CategoryScope::News
                : CategoryScope::Opinion;

            // أبقِ معرّف تصنيف ووردبريس الأصليّ (#2): إدراج صريح بـ id = wp_term_id
            // (incrementing=false ⇒ إدراج عاديّ يحتفظ بالمعرّف، لا alloc/lastInsertId).
            $category = new Category;
            $category->incrementing = false;
            $category->id = (int) $m->wp_term_id;
            $category->fill([
                'locale' => $locale,
                'scope' => $scope->value,
                'name' => $m->wp_name,
                'slug' => $this->slug($m, $locale),
                'parent_id' => $parentId,
                'status' => CategoryStatus::Active->value,
            ]);
            $category->save();

            $m->forceFill([
                'created_category_id' => $category->id,
                'target_category_id' => $category->id,
            ])->save();

            $catMemo[$wpTermId] = $category->id;
            $depthMemo[$wpTermId] = $depth;
            $created++;

            return ['id' => $category->id, 'depth' => $depth];
        };

        DB::transaction(function () use ($createRows, $resolve): void {
            // ترتيب حتميّ (معرّف المصدر تصاعدياً)؛ العودية تضمن الأب قبل الابن.
            foreach ($createRows->keys()->sort()->values() as $wpTermId) {
                $resolve((int) $wpTermId);
            }
        });

        // معرّفات التصنيفات محفوظة (= wp_term_id) — ارفع عدّاد الترقيم فوق أعلى معرّف
        // كي لا يصطدم تصنيف جديد (غير مُرحَّل) بالمعرّفات المحجوزة (قاعدة #6).
        MigrationSequence::realign('categories');

        // إنشاء الأهداف يغيّر التخطيط → أبطِل أي معاينة سابقة (المعاينة تأتي بعد الاستيراد).
        $run->forceFill(['mappings_updated_at' => now()])->save();

        $maps = $run->categoryMaps()->get();

        return ApiResponse::success(__('wp_migration.taxonomy.imported'), [
            'created' => $created,
            'reused' => $reused,
            'mapped' => $maps->filter(fn (MigrationCategoryMap $m): bool => $m->disposition === WpCategoryDisposition::Map)->count(),
            'excluded' => $maps->filter(fn (MigrationCategoryMap $m): bool => $m->disposition === WpCategoryDisposition::Exclude)->count(),
        ]);
    }

    /** slug حتميّ: المصدر المفكوك إن صحّ، وإلا من الاسم؛ عند الاصطلاح لاحقة «-wp-{term_id}». */
    private function slug(MigrationCategoryMap $m, string $locale): string
    {
        $base = trim(rawurldecode((string) ($m->wp_slug ?? '')));
        if ($base === '') {
            $base = SlugGenerator::makeWithFallback((string) $m->wp_name, '-');
        }

        $taken = fn (string $s): bool => Category::withTrashed()
            ->where('locale', $locale)->where('slug', $s)->exists();

        if (! $taken($base)) {
            return $base;
        }

        $suffixed = $base.'-wp-'.$m->wp_term_id; // لاحقة حتمية — لا عشوائية إطلاقاً
        if (! $taken($suffixed)) {
            return $suffixed;
        }

        // دفاعيّ بحت (اصطدام نادر مع اللاحقة نفسها): عدّاد حتميّ.
        $n = 2;
        while ($taken($suffixed.'-'.$n)) {
            $n++;
        }

        return $suffixed.'-'.$n;
    }
}
