<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Http\Resources\Admin\Content\TagResource;
use App\Models\Article;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Tags\Tag;

/**
 * قائمة الوسوم لإدارة الوسوم (لوحة الإدارة) — مرقّمة، مع عدّاد الاستخدام الحقيقيّ
 * (من taggables) مرتّبة بالأكثر استخداماً. بحث اختياريّ بالاسم ضمن اللغة المطلوبة.
 *
 * عدّاد الاستخدام عبر استعلام فرعيّ مرتبط (correlated) — صديق لكلٍّ من MySQL وSQLite،
 * ولا يتطلّب علاقة taggables على موديل Spatie.
 */
class ListManagedTagsAction
{
    public function handle(string $locale, string $search): JsonResponse
    {
        if (! in_array($locale, Article::LOCALES, true)) {
            return ApiResponse::error(__('tag.invalid_locale'), [], 422);
        }

        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));
        $search = trim($search);

        $query = Tag::query()
            ->select('tags.*')
            ->selectRaw('(select count(*) from taggables where taggables.tag_id = tags.id) as usage_count')
            ->orderByDesc('usage_count')
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where("name->{$locale}", 'like', '%'.$search.'%');
        }

        $tags = $query->paginate($perPage)->appends(request()->query());

        return ApiResponse::success(
            data: TagResource::collection($tags)->resolve(),
            meta: [
                'pagination' => [
                    'total' => $tags->total(),
                    'count' => $tags->count(),
                    'per_page' => $tags->perPage(),
                    'current_page' => $tags->currentPage(),
                    'total_pages' => $tags->lastPage(),
                ],
            ],
        );
    }
}
