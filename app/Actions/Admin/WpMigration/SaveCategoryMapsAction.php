<?php

declare(strict_types=1);

namespace App\Actions\Admin\WpMigration;

use App\Enums\ArticleType;
use App\Enums\CategoryScope;
use App\Enums\WpCategoryDisposition;
use App\Enums\WpCategoryMode;
use App\Models\Category;
use App\Models\MigrationCategoryMap;
use App\Models\MigrationRun;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * يحفظ تنسيب تصنيفات المصدر للتشغيلة (Step 4) على محورين صريحين:
 *   - disposition: create | map | exclude (تصرّف المُشغِّل).
 *   - mode: news | articles (نوع المحتوى للمُضمَّن).
 *
 * map يتطلّب هدفاً قائماً يطابق نطاقه النوع (يُفرَض قبل أي كتابة). create لا يتطلّب
 * هدفاً (يُنشئه ImportTaxonomyAction لاحقاً). exclude لا نوع ولا هدف. توافق رجعيّ:
 * غياب disposition يُستنتَج (مُضمَّن ⇒ map، وإلا exclude). upsert على (run_id, wp_term_id).
 */
class SaveCategoryMapsAction
{
    /** @param  array<int,array<string,mixed>>  $maps */
    public function handle(MigrationRun $run, array $maps): JsonResponse
    {
        // تحقّق دلاليّ لكل صفّ مُضمَّن — رفض مبكر بلا كتابة جزئية.
        foreach ($maps as $m) {
            $disposition = self::disposition($m);
            if (! $disposition->isIncluded()) {
                continue;
            }

            $mode = WpCategoryMode::from((string) $m['mode']);
            if (! $mode->isIncluded()) {
                return ApiResponse::error(__('wp_migration.map.type_required'), [], 422);
            }

            // create يُنشئ هدفه لاحقاً — لا تحقّق هدف الآن. map يتطلّب هدفاً مطابق النطاق.
            if ($disposition === WpCategoryDisposition::Map) {
                $targetId = $m['target_category_id'] ?? null;
                $target = $targetId !== null ? Category::query()->find($targetId) : null;
                if ($target === null) {
                    return ApiResponse::error(__('wp_migration.map.target_required'), [], 422);
                }

                $needScope = $mode->articleType() === ArticleType::News
                    ? CategoryScope::News
                    : CategoryScope::Opinion;

                if (! $target->scope->allowsArticleScope($needScope)) {
                    return ApiResponse::error(__('wp_migration.map.scope_mismatch'), [], 422);
                }
            }
        }

        DB::transaction(function () use ($run, $maps): void {
            foreach ($maps as $m) {
                $disposition = self::disposition($m);
                $included = $disposition->isIncluded();
                $mode = WpCategoryMode::from((string) $m['mode']);

                $values = [
                    'wp_name' => (string) $m['wp_name'],
                    'wp_slug' => $m['wp_slug'] ?? null,
                    'wp_parent_id' => $m['wp_parent_id'] ?? null,
                    'wp_count' => (int) ($m['wp_count'] ?? 0),
                    'mode' => $included ? $mode->value : WpCategoryMode::Exclude->value,
                    'disposition' => $disposition->value,
                ];

                // map: هدف قائم. exclude: امسح الهدف + رابط الإنشاء. create: اترك الهدف/الرابط
                // كما هما (إعادة استخدام idempotent للتصنيف المُنشأ عند الحفظ المتكرّر).
                if ($disposition === WpCategoryDisposition::Map) {
                    $values['target_category_id'] = (int) $m['target_category_id'];
                } elseif ($disposition === WpCategoryDisposition::Exclude) {
                    $values['target_category_id'] = null;
                    $values['created_category_id'] = null;
                }

                MigrationCategoryMap::updateOrCreate(
                    ['run_id' => $run->id, 'wp_term_id' => (int) $m['wp_term_id']],
                    $values,
                );
            }
        });

        // تغيّر التنسيب يُبطِل أي معاينة سابقة (تُصبح قديمة) ويمنع التنفيذ.
        $run->forceFill(['mappings_updated_at' => now()])->save();

        $saved = $run->categoryMaps()->get();

        return ApiResponse::success(__('wp_migration.map.saved'), [
            'count' => $saved->count(),
            'included' => $saved->filter(fn (MigrationCategoryMap $m): bool => $m->disposition->isIncluded())->count(),
            'create' => $saved->filter(fn (MigrationCategoryMap $m): bool => $m->disposition === WpCategoryDisposition::Create)->count(),
        ]);
    }

    /** يستنتج التصرّف عند غيابه (توافق رجعيّ): مُضمَّن النوع ⇒ map، وإلا exclude. */
    private static function disposition(array $m): WpCategoryDisposition
    {
        if (isset($m['disposition'])) {
            return WpCategoryDisposition::from((string) $m['disposition']);
        }

        return WpCategoryMode::from((string) $m['mode'])->isIncluded()
            ? WpCategoryDisposition::Map
            : WpCategoryDisposition::Exclude;
    }
}
