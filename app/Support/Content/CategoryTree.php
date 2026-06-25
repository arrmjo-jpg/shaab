<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Models\Category;
use Illuminate\Support\Collection;

/**
 * تجميع شجرة التصنيفات من مجموعة مسطّحة مرتّبة — استعلام واحد، بلا N+1.
 * أداة بنية تحتية مخصّصة (لا service عام).
 */
final class CategoryTree
{
    /**
     * @param  Collection<int, Category>  $flat  مرتّبة مسبقاً (sort_order)
     * @return Collection<int, Category> الجذور مع children مُحمَّلة تدريجياً
     */
    public static function build(Collection $flat): Collection
    {
        $byParent = $flat->groupBy(fn (Category $c): string => (string) ($c->parent_id ?? 'root'));

        $attach = function (Category $node) use (&$attach, $byParent): Category {
            $children = ($byParent->get((string) $node->id) ?? collect())
                ->map(fn (Category $c): Category => $attach($c))
                ->values();

            $node->setRelation('children', $children);

            return $node;
        };

        return ($byParent->get('root') ?? collect())
            ->map(fn (Category $c): Category => $attach($c))
            ->values();
    }
}
