<?php

declare(strict_types=1);

namespace App\Support\Epaper;

use App\Models\Epaper;
use App\Models\EpaperPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * بحث الجريدة الرقمية المدعوم بقاعدة البيانات فقط (LIKE على epaper_pages.text)، لا
 * Meilisearch. نطاقان: «داخل العدد» (Phase 4b — run) و«الأرشيف العابر» (Phase 6 —
 * archive/pageMatches). يولّد مقتطفاً حول أوّل تطابق + عدّ التطابقات. عربيّ-آمن (دوالّ
 * mb_*). الوصول/الخنق/بوابة الوحدة يفرضها المتحكّم والمسار — هذا الموصِّل منطق استعلام بحت.
 *
 * قرار البنية (Phase 6): لِمَ لا بنية بحث مخصّصة (Meilisearch/Scout) للأرشيف؟ الأرشيف
 * واقعيّاً مئات إلى بضعة آلاف الأعداد؛ مسح LIKE على epaper_pages مضموماً إلى epapers
 * (منشور + مستويات مرئيّة + مرشّحات) مع ترقيم وحصر «عدد لكل صفّ» سريعٌ مقبول. بنية بحث
 * منفصلة تُبرَّر فقط عند مقياس ضخم (عشرات الآلاف+) أو ترتيب-حسب-الصلة لغويّ — وكلاهما
 * خارج نطاق هذا المنتج الآن. DB-first يتجنّب بنية تشغيليّة وفهرسة مزدوجة بلا داعٍ.
 */
final class EpaperPageSearch
{
    public const PER_PAGE = 20;

    public const MAX_PER_PAGE = 50;

    /** نصف نافذة المقتطف (محارف) حول التطابق. */
    private const SNIPPET_RADIUS = 90;

    /** تهريب محارف LIKE الخاصّة (% _) بحرف هروب محايد «=» — متّسق عبر MySQL/SQLite. */
    private static function escapeLike(string $query): string
    {
        return str_replace(['=', '%', '_'], ['==', '=%', '=_'], $query);
    }

    /** صفحات العدد المطابِقة للاستعلام (LIKE)، مرقّمة، أحدثها صفحةً أولاً صعوداً. */
    public static function run(Epaper $epaper, string $query, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
        $escaped = self::escapeLike($query);

        return EpaperPage::query()
            ->where('epaper_id', $epaper->id)
            ->where('has_text', true)
            ->whereRaw("text LIKE ? ESCAPE '='", ['%'.$escaped.'%'])
            ->orderBy('page_number')
            ->paginate($perPage, ['id', 'page_number', 'text']);
    }

    /**
     * بحث الأرشيف العابر للأعداد (Phase 6) — يُرقّم «الأعداد» (لا الصفحات) كي لا يُغرِق
     * عددٌ واحد النتائج: كل صفّ عددٌ مطابِق واحد، أحدثها نشراً أولاً. يقصر على الأعداد
     * المنشورة ذات مستوى وصول مسموح بعرضه ($viewableLevels — يُحسَب في المتحكّم من
     * السياسة) فلا يُسرَّب نصّ عددٍ لا يُعرض. مرشّحات اختيارية: locale/issue_number/
     * date_from/date_to. الإثراء (الصفحة/المقتطف) عبر pageMatches() على أعداد الصفحة فقط.
     *
     * @param  array<int,string>  $viewableLevels  مستويات الوصول المسموح بها (قد تكون فارغة ⇒ لا نتائج)
     * @param  array<string,mixed>  $filters  locale|issue_number|date_from|date_to
     * @return LengthAwarePaginator<Epaper>
     */
    public static function archive(string $query, array $viewableLevels, array $filters = [], int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
        $escaped = self::escapeLike($query);

        $builder = Epaper::query()
            ->published()
            ->whereIn('access_level', $viewableLevels) // [] ⇒ تناقض ⇒ صفر نتائج (آمن)
            ->whereExists(function (QueryBuilder $q) use ($escaped): void {
                $q->selectRaw('1')
                    ->from('epaper_pages')
                    ->whereColumn('epaper_pages.epaper_id', 'epapers.id')
                    ->where('epaper_pages.has_text', true)
                    ->whereRaw("epaper_pages.text LIKE ? ESCAPE '='", ['%'.$escaped.'%']);
            });

        if (! empty($filters['locale'])) {
            $builder->forLocale((string) $filters['locale']);
        }
        if (! empty($filters['issue_number'])) {
            $builder->where('issue_number', (int) $filters['issue_number']);
        }
        if (! empty($filters['date_from'])) {
            $builder->whereDate('publication_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $builder->whereDate('publication_date', '<=', $filters['date_to']);
        }

        return $builder
            ->orderByDesc('publication_date')
            ->orderByDesc('issue_number')
            ->paginate($perPage);
    }

    /**
     * لأعداد الصفحة الحاليّة فقط: أوّل صفحة مطابِقة (للمقتطف والتنقّل الدقيق) + عدد
     * الصفحات المطابِقة داخل كل عدد. استعلامٌ واحد محدود بعدد أعداد الصفحة (≤ per_page).
     *
     * @param  array<int,int>  $issueIds
     * @return array<int,array{page:int,text:string,pages_matched:int}>
     */
    public static function pageMatches(array $issueIds, string $query): array
    {
        if ($issueIds === []) {
            return [];
        }

        $escaped = self::escapeLike($query);

        $rows = EpaperPage::query()
            ->whereIn('epaper_id', $issueIds)
            ->where('has_text', true)
            ->whereRaw("text LIKE ? ESCAPE '='", ['%'.$escaped.'%'])
            ->orderBy('epaper_id')
            ->orderBy('page_number')
            ->get(['epaper_id', 'page_number', 'text']);

        $map = [];
        foreach ($rows as $row) {
            $id = (int) $row->epaper_id;
            if (! isset($map[$id])) {
                $map[$id] = ['page' => (int) $row->page_number, 'text' => (string) $row->text, 'pages_matched' => 0];
            }
            $map[$id]['pages_matched']++;
        }

        return $map;
    }

    /** مقتطف نظيف حول أوّل تطابق (مع … عند الاقتطاع). */
    public static function snippet(string $text, string $query): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return '';
        }

        $window = self::SNIPPET_RADIUS * 2;
        $pos = $query !== '' ? mb_stripos($text, $query) : false;

        if ($pos === false) {
            $head = mb_substr($text, 0, $window);

            return $head.(mb_strlen($text) > mb_strlen($head) ? '…' : '');
        }

        $start = max(0, $pos - self::SNIPPET_RADIUS);
        $length = mb_strlen($query) + $window;
        $excerpt = trim(mb_substr($text, $start, $length));

        $prefix = $start > 0 ? '…' : '';
        $suffix = ($start + $length) < mb_strlen($text) ? '…' : '';

        return $prefix.$excerpt.$suffix;
    }

    /** عدد تطابقات الاستعلام في نصّ الصفحة (غير حسّاس لحالة الأحرف). */
    public static function matchCount(string $text, string $query): int
    {
        if ($query === '') {
            return 0;
        }

        return substr_count(mb_strtolower($text), mb_strtolower($query));
    }
}
