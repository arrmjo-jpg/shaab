<?php

declare(strict_types=1);

namespace App\Actions\Public\VideoLibrary;

use App\Http\Resources\Public\VideoLibrary\PublicVideoCardResource;
use App\Models\Video;
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
 * قائمة فيديوهات المكتبة العامة — عام + قابل للتشغيل فقط، بادئة locale، مرشّحات
 * allow-list (q/category-slug/source_type). كاش single-flight (CachedRead) ضدّ
 * عاصفة الطوابير، وترقيم offset (افتراضي) أو cursor (?paginate=cursor) لخلاصات
 * الجوّال (تمرير لا نهائي ثابت بلا COUNT/إزاحة). يتوافق بنيوياً مع بحث Scout القادم.
 */
class ListPublicVideosAction
{
    /** سقف معرّفات البحث من Scout قبل التقييد بالـ SQL (يطابق نمط المقالات). */
    private const SEARCH_LIMIT = 500;

    public function handle(string $locale, Request $request): JsonResponse
    {
        if (! in_array($locale, Video::LOCALES, true)) {
            return ApiResponse::error(__('video.invalid_locale'), [], 422);
        }

        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) $request->integer('per_page', $default), $max));
        $page = max(1, (int) $request->integer('page', 1));
        $cursorMode = $request->query('paginate') === 'cursor';
        $effectiveQ = $this->normalizeQ($request);

        $queryHash = $this->hashQuery($request, $effectiveQ, $perPage, $page, $cursorMode);

        $payload = CachedRead::remember(
            VideoCacheTags::feedTags($locale),
            CacheKeys::publicVideosList($locale, $queryHash),
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
            Video::query()
                ->public()
                ->playable()
                ->forLocale($locale)
                ->with(['mediaAsset', 'category', 'engagementCounter'])
        )
            ->allowedFilters(
                // النصّ مُطبَّع مسبقاً (≥ حرفين، مقصوص). بحث عبر Scout/Meilisearch
                // (متن كامل + تسامح أخطاء) ثم تقييد بالـ SQL (locale + public + playable
                // + بقية الفلاتر) عبر whereIn — الثوابت العامة تبقى مفروضة على القراءة.
                AllowedFilter::callback('q', function ($q) use ($effectiveQ): void {
                    if ($effectiveQ === '') {
                        return;
                    }
                    $ids = Video::search($effectiveQ)->take(self::SEARCH_LIMIT)->keys()->all();
                    $q->whereIn($q->getModel()->getQualifiedKeyName(), $ids ?: [-1]);
                }),
                AllowedFilter::callback('category', function ($q, $value): void {
                    $q->whereHas('category', fn ($c) => $c->where('slug', $value));
                }),
                AllowedFilter::exact('source_type'),
            );

        // cursor: ترتيب ثابت (published_at + id كفاصل) — لا COUNT ولا إزاحة عناصر.
        if ($cursorMode) {
            $paginator = $query
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->cursorPaginate($perPage)
                ->withQueryString();

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

        $paginator = $query
            ->allowedSorts('published_at', 'views_count')
            ->defaultSort('-published_at')
            ->paginate($perPage)
            ->appends($request->query());

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

    private function hashQuery(Request $request, string $effectiveQ, int $perPage, int $page, bool $cursorMode): string
    {
        $filter = $request->query('filter');
        $filter = is_array($filter) ? $filter : [];

        $relevant = [
            'page' => $page,
            'per_page' => $perPage,
            'paginate' => $cursorMode ? 'cursor' : '',
            'cursor' => (string) $request->query('cursor', ''),
            'sort' => (string) $request->query('sort', ''),
            // النصّ المُطبَّع (لا الخام) — تتلاشى المتغيّرات القصيرة/الفارغة لمفتاح واحد.
            'filter.q' => $effectiveQ,
            'filter.category' => (string) ($filter['category'] ?? ''),
            'filter.source_type' => (string) ($filter['source_type'] ?? ''),
        ];
        ksort($relevant);

        return substr(hash('xxh128', json_encode($relevant, JSON_THROW_ON_ERROR)), 0, 16);
    }

    /**
     * يطبّع نص البحث: تشذيب + حدّ أدنى حرفين (وإلا يُتجاهَل) + سقف 100 حرف. يمنع
     * استعلامات LIKE القصيرة عديمة الجدوى وتفجّر مفاتيح الكاش (حارس DoS رخيص).
     */
    private function normalizeQ(Request $request): string
    {
        $filter = $request->query('filter');
        $q = is_array($filter) ? trim((string) ($filter['q'] ?? '')) : '';

        return mb_strlen($q) >= 2 ? mb_substr($q, 0, 100) : '';
    }
}
