<?php

declare(strict_types=1);

namespace App\Support\Epaper;

use App\Models\Epaper;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Meilisearch\Exceptions\CommunicationException;
use Meilisearch\Exceptions\TimeOutException;

/**
 * بحث الأرشيف العابر — طبقة الاستعلام (Enterprise). تختار المسار حسب محرّك Scout:
 *
 *  • meilisearch (الإنتاج/المقياس): استعلام مباشر على فهرس الصفحات مع فرض الوصول
 *    **داخل المحرّك** (filter على access_level/locale ⇒ صفر تسريب)، distinct على
 *    epaper_id (نتيجة لكل عدد)، اقتطاع المقتطف من المحرّك، وتوزيع وُجوه epaper_id
 *    (عدد الصفحات المطابِقة). لا لمس لقاعدة البيانات على المسار الساخن (يفهرس مئات
 *    الآلاف من الأعداد دون حِمل DB).
 *  • سواه (نشرات صغيرة/اختبار): مسار قاعدة البيانات (EpaperPageSearch::archive) —
 *    يحفظ التوافق ويُبقي الاختبارات خضراء دون محرّك حيّ.
 *
 * تدهور لطيف: عند تعذّر المحرّك في الإنتاج لا نُرهق القاعدة بمسحٍ على ملايين الصفحات
 * افتراضياً (db_fallback=false ⇒ نتيجة فارغة + علم degraded + إنذار)؛ يُفعَّل الارتداد
 * للقاعدة فقط حين يكون آمناً (نشرات صغيرة). شكل الصفّ موحّد بين المسارين (UX ثابت).
 */
final class EpaperArchiveSearch
{
    /** الحدّ الفعّال لحجم الصفحة (من الطلب، مقيّداً بإعدادات الوحدة). */
    public static function perPage(?int $requested): int
    {
        $default = (int) config('epaper.search.per_page', 20);
        $max = (int) config('epaper.search.max_per_page', 50);

        return max(1, min($requested ?: $default, $max));
    }

    /**
     * @param  array<int,string>  $viewableLevels  مستويات الوصول المسموح بعرضها (قد تكون فارغة)
     * @param  array<string,mixed>  $filters  locale|issue_number|date_from|date_to
     * @return array{results:array<int,array<string,mixed>>,pagination:array<string,int>,engine:string,degraded:bool}
     */
    public static function run(string $query, array $viewableLevels, array $filters, int $perPage, int $page): array
    {
        if ($viewableLevels === []) {
            return self::emptyResult($perPage, 'none'); // لا مستوى مرئيّ ⇒ لا استعلام (آمن)
        }

        if (EpaperSearchIndexer::enabled()) {
            try {
                return self::viaEngine($query, $viewableLevels, $filters, $perPage, $page);
            } catch (CommunicationException|TimeOutException|ConnectException $e) {
                Log::warning('epaper.search.engine_unavailable', ['error' => $e->getMessage()]);

                return (bool) config('epaper.search.db_fallback', false)
                    ? self::viaDb($query, $viewableLevels, $filters, $perPage)
                    : self::degraded($perPage);
            }
        }

        return self::viaDb($query, $viewableLevels, $filters, $perPage);
    }

    // ─── محرّك Meilisearch ────────────────────────────────────────────────────

    /**
     * @param  array<int,string>  $levels
     * @param  array<string,mixed>  $filters
     * @return array{results:array<int,array<string,mixed>>,pagination:array<string,int>,engine:string,degraded:bool}
     */
    private static function viaEngine(string $query, array $levels, array $filters, int $perPage, int $page): array
    {
        $raw = EpaperSearchIndexer::index()->rawSearch($query, [
            'filter' => self::filterExpression($levels, $filters),
            'attributesToCrop' => ['text'],
            'cropLength' => max(10, (int) config('epaper.search.crop_length', 40)),
            'cropMarker' => '…',
            'facets' => ['epaper_id'],
            'page' => max(1, $page),
            'hitsPerPage' => $perPage,
        ]);

        return self::parseEngineResponse($raw, $query, $perPage, $page);
    }

    /**
     * تحويل استجابة Meilisearch الخام إلى صفوف + ترقيم. نقيّ (يقبل مصفوفة) ليُختبَر
     * بمُعطىً ثابت دون محرّك حيّ.
     *
     * @param  array<string,mixed>  $raw
     * @return array{results:array<int,array<string,mixed>>,pagination:array<string,int>,engine:string,degraded:bool}
     */
    public static function parseEngineResponse(array $raw, string $query, int $perPage, int $page): array
    {
        $hits = is_array($raw['hits'] ?? null) ? $raw['hits'] : [];
        $facets = $raw['facetDistribution']['epaper_id'] ?? [];

        $rows = [];
        foreach ($hits as $hit) {
            $epaperId = (int) ($hit['epaper_id'] ?? 0);
            $rows[] = self::row(
                $epaperId,
                (int) ($hit['issue_number'] ?? 0),
                (string) ($hit['issue_title'] ?? ''),
                (string) ($hit['locale'] ?? ''),
                (string) ($hit['access_level'] ?? ''),
                isset($hit['publication_date']) ? (int) $hit['publication_date'] : null,
                isset($hit['page_count']) ? (int) $hit['page_count'] : null,
                (string) ($hit['issue_slug'] ?? ''),
                (int) ($hit['page_number'] ?? 1),
                (string) ($hit['_formatted']['text'] ?? $hit['text'] ?? ''),
                (int) ($facets[(string) $epaperId] ?? 1),
                $query,
            );
        }

        return [
            'results' => $rows,
            'pagination' => [
                'total' => (int) ($raw['totalHits'] ?? count($rows)),
                'count' => count($rows),
                'per_page' => (int) ($raw['hitsPerPage'] ?? $perPage),
                'current_page' => (int) ($raw['page'] ?? $page),
                'total_pages' => (int) ($raw['totalPages'] ?? 1),
            ],
            'engine' => 'meilisearch',
            'degraded' => false,
        ];
    }

    /**
     * تعبير ترشيح Meilisearch — يفرض الوصول واللغة والمرشّحات الاختيارية داخل المحرّك.
     *
     * @param  array<int,string>  $levels
     * @param  array<string,mixed>  $filters
     */
    private static function filterExpression(array $levels, array $filters): string
    {
        $quoted = implode(', ', array_map(static fn (string $l): string => '"'.$l.'"', $levels));
        $parts = ['access_level IN ['.$quoted.']'];

        if (! empty($filters['locale'])) {
            $parts[] = 'locale = "'.$filters['locale'].'"';
        }
        if (! empty($filters['issue_number'])) {
            $parts[] = 'issue_number = '.(int) $filters['issue_number'];
        }
        if (! empty($filters['date_from'])) {
            $parts[] = 'publication_date >= '.Carbon::parse((string) $filters['date_from'], config('app.timezone'))->startOfDay()->getTimestamp();
        }
        if (! empty($filters['date_to'])) {
            $parts[] = 'publication_date <= '.Carbon::parse((string) $filters['date_to'], config('app.timezone'))->endOfDay()->getTimestamp();
        }

        return implode(' AND ', $parts);
    }

    // ─── مسار قاعدة البيانات (توافق + نشرات صغيرة) ─────────────────────────────

    /**
     * @param  array<int,string>  $levels
     * @param  array<string,mixed>  $filters
     * @return array{results:array<int,array<string,mixed>>,pagination:array<string,int>,engine:string,degraded:bool}
     */
    private static function viaDb(string $query, array $levels, array $filters, int $perPage): array
    {
        $paginator = EpaperPageSearch::archive($query, $levels, $filters, $perPage);
        $issueIds = $paginator->getCollection()->map(static fn (Epaper $e): int => $e->id)->all();
        $matches = EpaperPageSearch::pageMatches($issueIds, $query);

        $rows = $paginator->getCollection()->map(function (Epaper $e) use ($matches, $query): array {
            $m = $matches[$e->id] ?? null;

            return self::row(
                $e->id,
                (int) $e->issue_number,
                (string) $e->title,
                (string) $e->locale,
                $e->access_level->value,
                $e->publication_date?->getTimestamp(),
                $e->page_count,
                (string) $e->slug,
                (int) ($m['page'] ?? 1),
                EpaperPageSearch::snippet((string) ($m['text'] ?? ''), $query),
                (int) ($m['pages_matched'] ?? 0),
                $query,
            );
        })->values()->all();

        return [
            'results' => $rows,
            'pagination' => [
                'total' => $paginator->total(),
                'count' => $paginator->count(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
            ],
            'engine' => 'database',
            'degraded' => false,
        ];
    }

    // ─── صفّ نتيجة موحّد + حالات فارغة ─────────────────────────────────────────

    /** @return array<string,mixed> */
    private static function row(
        int $epaperId,
        int $issueNumber,
        string $title,
        string $locale,
        string $accessLevel,
        ?int $publicationTs,
        ?int $pageCount,
        string $slug,
        int $page,
        string $snippet,
        int $pagesMatched,
        string $query,
    ): array {
        $page = max(1, $page);
        $base = '/'.trim("{$locale}/epaper/{$epaperId}-{$slug}", '/');

        return [
            'id' => $epaperId,
            'issue_number' => $issueNumber,
            'title' => $title,
            'locale' => $locale,
            'access_level' => $accessLevel,
            'publication_date' => $publicationTs !== null
                ? Carbon::createFromTimestamp($publicationTs, config('app.timezone'))->toDateString()
                : null,
            'page_count' => $pageCount,
            'match' => [
                'page' => $page,
                'snippet' => $snippet,
                'pages_matched' => $pagesMatched,
            ],
            'url' => $base.($page > 1 ? "/p/{$page}" : '').'?q='.rawurlencode($query),
            'path' => $base,
        ];
    }

    /** @return array{results:array<int,array<string,mixed>>,pagination:array<string,int>,engine:string,degraded:bool} */
    private static function degraded(int $perPage): array
    {
        return ['results' => [], 'pagination' => self::emptyPagination($perPage), 'engine' => 'unavailable', 'degraded' => true];
    }

    /** @return array{results:array<int,array<string,mixed>>,pagination:array<string,int>,engine:string,degraded:bool} */
    private static function emptyResult(int $perPage, string $engine): array
    {
        return ['results' => [], 'pagination' => self::emptyPagination($perPage), 'engine' => $engine, 'degraded' => false];
    }

    /** @return array<string,int> */
    private static function emptyPagination(int $perPage): array
    {
        return ['total' => 0, 'count' => 0, 'per_page' => $perPage, 'current_page' => 1, 'total_pages' => 0];
    }
}
