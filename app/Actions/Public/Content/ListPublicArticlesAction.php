<?php

declare(strict_types=1);

namespace App\Actions\Public\Content;

use App\Enums\ArticleType;
use App\Http\Resources\Public\Content\PublicArticleListItemResource;
use App\Models\Article;
use App\Models\Category;
use App\Support\Cache\ArticleCacheTags;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * قائمة المقالات المنشورة (قراءة عامة) — مرشّحات allow-list فقط.
 *
 * - locale يأتي من بادئة المسار {locale}؛ يُتحقَّق منه قبل أي استعلام.
 * - الفلاتر المسموحة: type, category (slug), tag (name), q (بحث في العنوان).
 * - ترتيب افتراضي: -published_at؛ سماح: -published_at, published_at, -views_count.
 * - الكاش مفعّل عبر tag «articles» — يُفرَّغ من إجراءات الإدارة (Wave C2 امتداد).
 * - مفتاح الكاش يحوي query-hash مستقرّاً (لا يعتمد ترتيب المفاتيح).
 */
class ListPublicArticlesAction
{
    public function handle(string $locale, Request $request): JsonResponse
    {
        if (! in_array($locale, Article::LOCALES, true)) {
            return ApiResponse::error(__('article.invalid_locale'), [], 422);
        }

        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) $request->integer('per_page', $default), $max));
        $page = max(1, (int) $request->integer('page', 1));

        // وضع cursor (للجوّال/التمرير العميق): ثابت وسريع بلا COUNT ولا إزاحة عناصر.
        $cursorMode = $request->query('paginate') === 'cursor';

        $queryHash = $this->hashQuery($request, $perPage, $page);

        // إبطال حبيبي: قائمة مفلترة بتصنيف تُوسَم بوسم ذلك التصنيف (تُبطَل عند تغيّر
        // مقالاته فقط)؛ القوائم العامة تُوسَم feed(locale).
        $categoryFilter = (string) ($request->query('filter')['category'] ?? '');
        $tags = $categoryFilter !== ''
            ? ArticleCacheTags::categoryTags($locale, $categoryFilter)
            : ArticleCacheTags::feedTags($locale);

        $payload = CachedRead::remember(
            $tags,
            CacheKeys::publicArticlesList($locale, $queryHash),
            CacheTtl::SHORT,
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
        // بحث (filter[q]) يُحسب مرّة: Meilisearch يُعيد المعرّفات **مرتّبةً بالصلة** (العنوان موزون أوّلًا).
        // نُبقي هذا الترتيب بدل التاريخ — وإلا يُدفَن أفضل مطابق للعنوان تحت الأحدث («بحث غبي»).
        // null = لا بحث · [] = بحث بلا نتائج (أو محرّك معطّل، مُسجَّل).
        $searchIds = $this->searchIds($request);

        $query = QueryBuilder::for(
            Article::query()
                ->published()
                ->forLocale($locale)
                ->with(['author:id,name,avatar,is_writer', 'primaryCategory:id,name,slug', 'mediaAssets' => fn ($q) => $q->wherePivot('collection', 'cover')])
        )
            ->allowedFilters(
                AllowedFilter::exact('type'),
                AllowedFilter::partial('title', 'title'),
                AllowedFilter::callback('q', function ($q) use ($searchIds): void {
                    if ($searchIds === null) {
                        return; // لا بحث
                    }
                    // يُقيَّد الناتج بمعرّفات Meilisearch (ضمن باقي الفلاتر/اللغة)؛ الترتيب بالصلة أدناه.
                    $q->whereIn($q->getModel()->getQualifiedKeyName(), $searchIds ?: [-1]);
                }),
                AllowedFilter::callback('category', function ($q, $value) use ($locale): void {
                    $category = Category::query()
                        ->where('slug', (string) $value)
                        ->where('locale', $locale)
                        ->first();
                    if ($category === null) {
                        $q->whereRaw('1 = 0'); // empty set rather than 500

                        return;
                    }
                    $q->where(function ($w) use ($category): void {
                        $w->where('primary_category_id', $category->id)
                            ->orWhereHas(
                                'categories',
                                fn ($sub) => $sub->where('categories.id', $category->id)
                            );
                    });
                }),
                AllowedFilter::callback('tag', function ($q, $value): void {
                    $q->withAnyTags([(string) $value]);
                }),
            );

        // ── cursor: ترتيب ثابت (published_at + id كفاصل) — لا COUNT ولا إزاحة ──
        // is_pinned أولاً: المثبَّت يتصدّر قائمة قسمه/الخلاصة قبل الأحدث.
        if ($cursorMode) {
            $paginator = $query
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->cursorPaginate($perPage)
                ->withQueryString();

            return [
                'data' => PublicArticleListItemResource::collection($paginator)->resolve(),
                'cursor' => [
                    'per_page' => $paginator->perPage(),
                    'next_cursor' => $paginator->nextCursor()?->encode(),
                    'prev_cursor' => $paginator->previousCursor()?->encode(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ];
        }

        // ── offset (افتراضي للإدارة/الـ SEO): يشمل total/last_page ──
        // عند البحث: ترتيب صلة Meilisearch (FIELD على المعرّفات) — يتجاوز التثبيت/التاريخ كي لا
        // يُدفَن أفضل مطابق. غير البحث: is_pinned أوّلاً ثمّ التاريخ (سلوك الخلاصات القائم).
        if ($searchIds !== null && $searchIds !== []) {
            // ترتيب صلة Meilisearch محمول (MySQL + SQLite الاختبارات): CASE بدل FIELD (FIELD لا توجد
            // في SQLite). المعرّفات أعداد صحيحة (آمنة للإدراج المباشر، لا حقن).
            $key = (new Article)->getQualifiedKeyName();
            $cases = '';
            foreach (array_values($searchIds) as $pos => $id) {
                $cases .= ' WHEN '.(int) $id.' THEN '.$pos;
            }
            $paginator = $query
                ->orderByRaw("CASE {$key}{$cases} END")
                ->paginate($perPage)
                ->appends($request->query());
        } else {
            $paginator = $query
                ->orderByDesc('is_pinned')
                ->allowedSorts('published_at', 'views_count')
                ->defaultSort('-published_at')
                ->paginate($perPage)
                ->appends($request->query());
        }

        return [
            'data' => PublicArticleListItemResource::collection($paginator)->resolve(),
            'pagination' => [
                'total' => $paginator->total(),
                'count' => $paginator->count(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
            ],
        ];
    }

    /** معرّفات نتائج البحث مرتّبةً بالصلة (Meilisearch). null=لا بحث · []=بلا نتائج/محرّك معطّل (مُسجَّل، تدهور رشيق). */
    private function searchIds(Request $request): ?array
    {
        $term = trim((string) ($request->query('filter')['q'] ?? ''));
        if ($term === '') {
            return null;
        }
        try {
            return Article::search($term)->take(500)->keys()->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /** هاش مستقرّ لعبور الكاش — يستثني المفاتيح غير المؤثرة. */
    private function hashQuery(Request $request, int $perPage, int $page): string
    {
        $relevant = [
            'page' => $page,
            'per_page' => $perPage,
            'paginate' => (string) $request->query('paginate', ''),
            'cursor' => (string) $request->query('cursor', ''),
            'sort' => (string) $request->query('sort', ''),
            'filter.type' => (string) ($request->query('filter')['type'] ?? ''),
            'filter.category' => (string) ($request->query('filter')['category'] ?? ''),
            'filter.tag' => (string) ($request->query('filter')['tag'] ?? ''),
            'filter.q' => (string) ($request->query('filter')['q'] ?? ''),
        ];
        ksort($relevant);

        return substr(hash('xxh128', json_encode($relevant, JSON_THROW_ON_ERROR)), 0, 16);
    }

    /** @return array<int,string> */
    public static function allowedTypes(): array
    {
        return ArticleType::values();
    }
}
