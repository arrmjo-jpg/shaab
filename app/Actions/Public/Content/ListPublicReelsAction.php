<?php

declare(strict_types=1);

namespace App\Actions\Public\Content;

use App\Http\Resources\Public\Content\PublicReelResource;
use App\Models\Reel;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Cache\ReelCacheTags;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * قائمة الريلز المنشورة (قراءة عامة) — منشور فقط، بادئة locale، مرشّحات allow-list.
 * كاش single-flight (CachedRead) ضدّ عاصفة الطوابير، وترقيم offset (افتراضي) أو
 * cursor (?paginate=cursor) لخلاصات الجوّال (تمرير لا نهائي ثابت بلا COUNT/إزاحة).
 */
class ListPublicReelsAction
{
    public function handle(string $locale, Request $request): JsonResponse
    {
        if (! in_array($locale, Reel::LOCALES, true)) {
            return ApiResponse::error(__('article.invalid_locale'), [], 422);
        }

        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) $request->integer('per_page', $default), $max));
        $page = max(1, (int) $request->integer('page', 1));
        $cursorMode = $request->query('paginate') === 'cursor';

        $queryHash = $this->hashQuery($request, $perPage, $page, $cursorMode);

        $payload = CachedRead::remember(
            ReelCacheTags::feedTags($locale),
            CacheKeys::publicReelsList($locale, $queryHash),
            CacheTtl::REALTIME,
            fn (): array => $this->build($locale, $request, $perPage, $cursorMode)
        );

        return ApiResponse::success(
            data: $payload['data'],
            meta: $cursorMode ? ['cursor' => $payload['cursor']] : ['pagination' => $payload['pagination']]
        );
    }

    /** @return array<string,mixed> */
    private function build(string $locale, Request $request, int $perPage, bool $cursorMode): array
    {
        $query = QueryBuilder::for(
            Reel::query()
                ->published()
                ->forLocale($locale)
                ->with(['mediaAsset', 'engagementCounter'])
        )
            ->allowedFilters(
                AllowedFilter::callback('q', function ($q, $value): void {
                    $q->where(function ($w) use ($value): void {
                        $w->where('title', 'like', "%{$value}%")
                            ->orWhere('description', 'like', "%{$value}%");
                    });
                }),
            );

        // cursor: ترتيب ثابت (published_at + id كفاصل) — لا COUNT ولا إزاحة عناصر.
        if ($cursorMode) {
            $paginator = $query
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->cursorPaginate($perPage)
                ->withQueryString();

            return [
                'data' => PublicReelResource::collection($paginator)->resolve(),
                'cursor' => [
                    'per_page' => $paginator->perPage(),
                    'next_cursor' => $paginator->nextCursor()?->encode(),
                    'prev_cursor' => $paginator->previousCursor()?->encode(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ];
        }

        $paginator = $query
            ->allowedSorts('published_at')
            ->defaultSort('-published_at')
            ->paginate($perPage)
            ->appends($request->query());

        return [
            'data' => PublicReelResource::collection($paginator)->resolve(),
            'pagination' => [
                'total' => $paginator->total(),
                'count' => $paginator->count(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
            ],
        ];
    }

    private function hashQuery(Request $request, int $perPage, int $page, bool $cursorMode): string
    {
        $relevant = [
            'page' => $page,
            'per_page' => $perPage,
            'paginate' => $cursorMode ? 'cursor' : '',
            'cursor' => (string) $request->query('cursor', ''),
            'sort' => (string) $request->query('sort', ''),
            'filter.q' => (string) ($request->query('filter')['q'] ?? ''),
        ];
        ksort($relevant);

        return substr(hash('xxh128', json_encode($relevant, JSON_THROW_ON_ERROR)), 0, 16);
    }
}
