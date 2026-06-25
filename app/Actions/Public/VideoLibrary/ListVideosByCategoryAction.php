<?php

declare(strict_types=1);

namespace App\Actions\Public\VideoLibrary;

use App\Http\Resources\Public\VideoLibrary\PublicVideoCardResource;
use App\Models\Video;
use App\Models\VideoCategory;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Cache\VideoCacheTags;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * خلاصة تصنيف فيديو — فيديوهات تصنيف نشِط معيّن (بالـ slug)، عام + قابل للتشغيل،
 * بادئة locale، ترقيم offset/cursor. التصنيف المنشور (is_active) فقط؛ غيره ⇒ 404.
 * المطابقة مباشرة على video_category_id (الفيديو ينتمي لتصنيف واحد). مُوسَم بوسم
 * التصنيف + خلاصة اللغة فيُبطَل عند أي كتابة فيديو في اللغة أو هذا التصنيف تحديداً.
 */
class ListVideosByCategoryAction
{
    public function handle(string $locale, string $categorySlug, Request $request): JsonResponse
    {
        if (! in_array($locale, Video::LOCALES, true)) {
            return ApiResponse::error(__('video.invalid_locale'), [], 422);
        }

        $category = VideoCategory::query()
            ->active()
            ->forLocale($locale)
            ->where('slug', $categorySlug)
            ->first();

        if ($category === null) {
            return ApiResponse::error(__('video_category.not_found'), [], 404);
        }

        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) $request->integer('per_page', $default), $max));
        $page = max(1, (int) $request->integer('page', 1));
        $cursorMode = $request->query('paginate') === 'cursor';

        $queryHash = substr(hash('xxh128', json_encode([
            'page' => $page, 'per_page' => $perPage,
            'paginate' => $cursorMode ? 'cursor' : '',
            'cursor' => (string) $request->query('cursor', ''),
        ], JSON_THROW_ON_ERROR)), 0, 16);

        $payload = CachedRead::remember(
            array_merge(VideoCacheTags::feedTags($locale), [VideoCacheTags::category($locale, $categorySlug)]),
            CacheKeys::publicVideosByCategory($locale, $categorySlug, $queryHash),
            CacheTtl::REALTIME,
            fn (): array => $this->build($locale, (int) $category->id, $perPage, $cursorMode)
        );

        return ApiResponse::success(
            data: $payload['data'],
            meta: array_merge(
                $cursorMode ? ['cursor' => $payload['cursor']] : ['pagination' => $payload['pagination']],
                ['category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                ]],
            )
        );
    }

    /** @return array<string,mixed> */
    private function build(string $locale, int $categoryId, int $perPage, bool $cursorMode): array
    {
        $query = Video::query()
            ->public()
            ->playable()
            ->forLocale($locale)
            ->where('video_category_id', $categoryId)
            ->with(['mediaAsset', 'category', 'engagementCounter']);

        if ($cursorMode) {
            $paginator = $query
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->cursorPaginate($perPage);

            return [
                'data' => PublicVideoCardResource::collection($paginator)->resolve(),
                'cursor' => [
                    'per_page' => $paginator->perPage(),
                    'next_cursor' => $paginator->nextCursor()?->encode(),
                    'prev_cursor' => $paginator->previousCursor()?->encode(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ];
        }

        $paginator = $query->orderByDesc('published_at')->paginate($perPage);

        return [
            'data' => PublicVideoCardResource::collection($paginator)->resolve(),
            'pagination' => [
                'total' => $paginator->total(),
                'count' => $paginator->count(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
            ],
        ];
    }
}
