<?php

declare(strict_types=1);

namespace App\Actions\Public\Broadcast;

use App\Enums\BroadcastKind;
use App\Http\Resources\Public\Broadcast\PublicBroadcastCardResource;
use App\Models\Broadcast;
use App\Support\Cache\BroadcastCacheTags;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * قائمة البثّ العامة لنوع واحد (/live · /tv · /radio). الرؤية تعتمد على النوع
 * (publiclyListed): live أحداث عابرة (scheduled|live)؛ tv/radio دليل دائم يُبقي القناة
 * ظاهرة رغم offline/failed. كاش single-flight (CachedRead) + REALTIME TTL يحدّ التقادم،
 * مع إبطال فوري عند تحوّلات B2/B3 (feedTags(kind)). ترقيم offset (افتراضي) أو
 * cursor (?paginate=cursor) لخلاصات الجوّال. ترشيح بالتصنيف؛ لا بحث (لا فهرس Scout).
 */
class ListPublicBroadcastsAction
{
    public function handle(string $kind, Request $request): JsonResponse
    {
        if (BroadcastKind::tryFrom($kind) === null) {
            return ApiResponse::error(__('broadcast.not_found'), [], 404);
        }

        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) $request->integer('per_page', $default), $max));
        $page = max(1, (int) $request->integer('page', 1));
        $cursorMode = $request->query('paginate') === 'cursor';

        $queryHash = $this->hashQuery($request, $perPage, $page, $cursorMode);

        $payload = CachedRead::remember(
            BroadcastCacheTags::feedTags($kind),
            CacheKeys::publicBroadcastsList($kind, $queryHash),
            CacheTtl::REALTIME,
            fn (): array => $this->build($kind, $request, $perPage, $cursorMode)
        );

        return ApiResponse::success(
            data: $payload['data'],
            meta: $cursorMode ? ['cursor' => $payload['cursor']] : ['pagination' => $payload['pagination']]
        );
    }

    /** @return array<string,mixed> */
    private function build(string $kind, Request $request, int $perPage, bool $cursorMode): array
    {
        $query = QueryBuilder::for(
            Broadcast::query()
                ->publiclyListed($kind)
                ->with(['category', 'engagementCounter', 'cover'])
        )
            ->allowedFilters(
                AllowedFilter::callback('category', function ($q, $value): void {
                    $q->whereHas('category', fn ($c) => $c->where('slug', $value));
                }),
            );

        // cursor: ترتيب ثابت (sort_order + id كفاصل) — لا COUNT ولا إزاحة عناصر.
        if ($cursorMode) {
            $paginator = $query
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->cursorPaginate($perPage)
                ->withQueryString();

            return [
                'data' => PublicBroadcastCardResource::collection($paginator)->resolve(),
                'cursor' => [
                    'per_page' => $paginator->perPage(),
                    'next_cursor' => $paginator->nextCursor()?->encode(),
                    'prev_cursor' => $paginator->previousCursor()?->encode(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ];
        }

        $paginator = $query
            ->allowedSorts('sort_order', 'scheduled_at', 'started_at', 'viewer_count')
            ->defaultSort('sort_order', '-id')
            ->paginate($perPage)
            ->appends($request->query());

        return [
            'data' => PublicBroadcastCardResource::collection($paginator)->resolve(),
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
        $filter = $request->query('filter');
        $filter = is_array($filter) ? $filter : [];

        $relevant = [
            'page' => $page,
            'per_page' => $perPage,
            'paginate' => $cursorMode ? 'cursor' : '',
            'cursor' => (string) $request->query('cursor', ''),
            'sort' => (string) $request->query('sort', ''),
            'filter.category' => (string) ($filter['category'] ?? ''),
        ];
        ksort($relevant);

        return substr(hash('xxh128', json_encode($relevant, JSON_THROW_ON_ERROR)), 0, 16);
    }
}
