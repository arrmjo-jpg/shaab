<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Models\Article;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Tags\Tag;

/**
 * بحث/اقتراح وسوم للمحرّر الإداري (allow-list locale من Article::LOCALES).
 *
 * - بدون كاش: مجموعة صغيرة ومتغيّرة، الاستعلام مفهرس على ('type','order_column').
 * - lookup اللغة عبر JSON: name->{locale} — Spatie Tags يخزّن name كـ JSON.
 */
class ListTagsAction
{
    public function handle(string $locale, string $query, int $limit): JsonResponse
    {
        if (! in_array($locale, Article::LOCALES, true)) {
            return ApiResponse::error(__('tag.invalid_locale'), [], 422);
        }

        $limit = max(1, min($limit, 50));
        $query = trim($query);

        $q = Tag::query()
            ->select(['id', 'name', 'slug'])
            ->orderBy('order_column')
            ->orderBy('id')
            ->limit($limit);

        if ($query !== '') {
            $q->where("name->{$locale}", 'like', '%'.$query.'%');
        }

        $rows = $q->get()->map(fn (Tag $t): array => [
            'id' => $t->id,
            'name' => $t->getTranslation('name', $locale),
            'slug' => $t->getTranslation('slug', $locale),
        ])->values();

        return ApiResponse::success(data: $rows);
    }
}
