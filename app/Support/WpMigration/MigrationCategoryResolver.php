<?php

declare(strict_types=1);

namespace App\Support\WpMigration;

use App\Enums\ArticleType;
use App\Enums\ConflictPolicy;

/**
 * يحسم نوع المنشور وتصنيفاته من تنسيب المُشغِّل + سياسة التعارض. نقيّ (لا قاعدة بيانات).
 *
 * القواعد المقفولة:
 *  - يُحفَظ كل تصنيف مُنسَّب فريد (رئيسي + ثانوية بلا حدّ).
 *  - تعارض (أنواع مختلطة): prefer_news → News ويُبقي الأخبار فقط؛ prefer_articles →
 *    Opinion ويُبقي المقالات فقط؛ exclude → null (يُتخطّى). لا احتفاظ بنوع مختلط.
 *  - الرئيسي: تصنيف Yoast الرئيسي إن كان ضمن المُبقاة، وإلا الأعلى وزناً (الأكبر شجرةً)
 *    وعند التعادل الأصغر term_id (حتميّ).
 */
final class MigrationCategoryResolver
{
    /**
     * @param  array<int,int>  $postTtids  معرّفات term_taxonomy لتصنيفات المنشور
     * @param  array<int,array{type:string,target:int,weight:int,term_id:int}>  $mapByTtid  التنسيب المُضمَّن مفترَساً بـ ttid
     * @param  ?int  $yoastPrimaryTtid  ttid تصنيف Yoast الرئيسي للمنشور (إن وُجد)
     */
    public static function resolve(
        array $postTtids,
        array $mapByTtid,
        ConflictPolicy $policy,
        ?int $yoastPrimaryTtid = null,
    ): ?ResolvedCategories {
        // إبقاء تصنيفات المنشور المُنسَّبة فقط.
        $kept = array_values(array_filter($postTtids, static fn (int $t): bool => isset($mapByTtid[$t])));
        if ($kept === []) {
            return null; // ليس ضمن أيّ تصنيف مختار
        }

        $types = array_values(array_unique(array_map(static fn (int $t): string => $mapByTtid[$t]['type'], $kept)));

        if (count($types) > 1) {
            // تعارض — يُحسَم بالسياسة (لا تخمين).
            $targetType = match ($policy) {
                ConflictPolicy::PreferNews => 'news',
                ConflictPolicy::PreferArticles => 'articles',
                ConflictPolicy::Exclude => null,
            };
            if ($targetType === null) {
                return null; // مُستبعَد صراحةً
            }
            $kept = array_values(array_filter($kept, static fn (int $t): bool => $mapByTtid[$t]['type'] === $targetType));
        } else {
            $targetType = $types[0];
        }

        $primary = self::primary($kept, $mapByTtid, $yoastPrimaryTtid);

        $allTargets = array_values(array_unique(array_map(static fn (int $t): int => $mapByTtid[$t]['target'], $kept)));
        $secondary = array_values(array_filter($allTargets, static fn (int $id): bool => $id !== $primary));

        return new ResolvedCategories(
            $targetType === 'articles' ? ArticleType::Opinion : ArticleType::News,
            $primary,
            $secondary,
        );
    }

    /**
     * @param  array<int,int>  $kept
     * @param  array<int,array{type:string,target:int,weight:int,term_id:int}>  $mapByTtid
     */
    private static function primary(array $kept, array $mapByTtid, ?int $yoastPrimaryTtid): int
    {
        if ($yoastPrimaryTtid !== null && in_array($yoastPrimaryTtid, $kept, true)) {
            return $mapByTtid[$yoastPrimaryTtid]['target'];
        }

        // fallback حتميّ: الأعلى وزناً، ثم الأصغر term_id.
        usort($kept, static function (int $a, int $b) use ($mapByTtid): int {
            $byWeight = $mapByTtid[$b]['weight'] <=> $mapByTtid[$a]['weight'];

            return $byWeight !== 0 ? $byWeight : ($mapByTtid[$a]['term_id'] <=> $mapByTtid[$b]['term_id']);
        });

        return $mapByTtid[$kept[0]]['target'];
    }
}
