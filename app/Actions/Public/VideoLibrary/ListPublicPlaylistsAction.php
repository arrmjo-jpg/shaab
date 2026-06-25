<?php

declare(strict_types=1);

namespace App\Actions\Public\VideoLibrary;

use App\Http\Resources\Public\VideoLibrary\PublicPlaylistResource;
use App\Models\VideoPlaylist;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Cache\VideoCacheTags;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * قائمة قوائم التشغيل العامة — منشورة + عامة، بادئة locale، مرشّح q. لكل قائمة عدّاد
 * أعضائها العامين القابلين للتشغيل فقط (withCount مُقيَّد) — دون تحميل الفيديوهات
 * كاملة (خفّة الخلاصة). ترقيم offset (افتراضي) أو cursor. كاش single-flight.
 */
class ListPublicPlaylistsAction
{
    public function handle(string $locale, Request $request): JsonResponse
    {
        if (! in_array($locale, VideoPlaylist::LOCALES, true)) {
            return ApiResponse::error(__('video.invalid_locale'), [], 422);
        }

        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) $request->integer('per_page', $default), $max));
        $page = max(1, (int) $request->integer('page', 1));
        $cursorMode = $request->query('paginate') === 'cursor';

        $effectiveQ = $this->normalizeQ($request);
        $queryHash = substr(hash('xxh128', json_encode([
            'page' => $page, 'per_page' => $perPage,
            'paginate' => $cursorMode ? 'cursor' : '',
            'cursor' => (string) $request->query('cursor', ''),
            'q' => $effectiveQ,
        ], JSON_THROW_ON_ERROR)), 0, 16);

        $payload = CachedRead::remember(
            VideoCacheTags::feedTags($locale),
            CacheKeys::publicPlaylistsList($locale, $queryHash),
            CacheTtl::REALTIME,
            fn (): array => $this->build($locale, $request, $effectiveQ, $perPage, $cursorMode)
        );

        return ApiResponse::success(
            data: $payload['data'],
            meta: $cursorMode ? ['cursor' => $payload['cursor']] : ['pagination' => $payload['pagination']]
        );
    }

    /** @return array<string,mixed> */
    private function build(string $locale, Request $request, string $effectiveQ, int $perPage, bool $cursorMode): array
    {
        $query = QueryBuilder::for(
            VideoPlaylist::query()
                ->public()
                ->forLocale($locale)
                ->with(['cover'])
                // عدّاد الأعضاء العامين القابلين للتشغيل فقط (لا يُحتسَب مسودة/خاص).
                ->withCount(['videos as videos_count' => fn ($q) => $q->public()->playable()])
        )
            ->allowedFilters(
                // النصّ مُطبَّع مسبقاً (≥ حرفين، مقصوص) — يُتجاهَل القصير.
                AllowedFilter::callback('q', function ($q) use ($effectiveQ): void {
                    if ($effectiveQ === '') {
                        return;
                    }
                    $q->where(function ($w) use ($effectiveQ): void {
                        $w->where('title', 'like', "%{$effectiveQ}%")
                            ->orWhere('description', 'like', "%{$effectiveQ}%");
                    });
                }),
            );

        if ($cursorMode) {
            $paginator = $query
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->cursorPaginate($perPage)
                ->withQueryString();

            return [
                'data' => PublicPlaylistResource::collection($paginator)->resolve(),
                'cursor' => [
                    'per_page' => $paginator->perPage(),
                    'next_cursor' => $paginator->nextCursor()?->encode(),
                    'prev_cursor' => $paginator->previousCursor()?->encode(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ];
        }

        $paginator = $query
            ->allowedSorts('published_at', 'sort_order')
            ->defaultSort('-published_at')
            ->paginate($perPage)
            ->appends($request->query());

        return [
            'data' => PublicPlaylistResource::collection($paginator)->resolve(),
            'pagination' => [
                'total' => $paginator->total(),
                'count' => $paginator->count(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
            ],
        ];
    }

    /** يطبّع نص البحث: تشذيب + حدّ أدنى حرفين + سقف 100 حرف (حارس DoS رخيص). */
    private function normalizeQ(Request $request): string
    {
        $filter = $request->query('filter');
        $q = is_array($filter) ? trim((string) ($filter['q'] ?? '')) : '';

        return mb_strlen($q) >= 2 ? mb_substr($q, 0, 100) : '';
    }
}
