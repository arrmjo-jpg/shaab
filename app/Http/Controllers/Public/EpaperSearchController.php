<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\EpaperAccessLevel;
use App\Enums\EpaperTextLayer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\EpaperSearchRequest;
use App\Http\Resources\Public\EpaperSearchResultResource;
use App\Models\Epaper;
use App\Support\Epaper\EpaperAccessPolicy;
use App\Support\Epaper\EpaperPageSearch;
use Illuminate\Http\JsonResponse;

/**
 * بحث «داخل العدد» (Phase 4b) — JSON. النطاق عددٌ واحد منشور فقط (لا أرشيف عابر).
 * يحترم EpaperAccessPolicy::canView (خاصّ → 404، مشترك بلا استحقاق → 403) تماماً
 * كنقطة تسليم الوثيقة، فلا يُسرَّب نصّ عددٍ لا يُسمح بعرضه. الخنق وبوابة الوحدة
 * (throttle:public.read + newspaper.enabled) من مجموعة المسار. تعطّل OCR ⇒ نتيجة
 * فارغة لطيفة (searchable=false) لا خطأ.
 */
class EpaperSearchController extends Controller
{
    public function search(EpaperSearchRequest $request, string $locale, string $issue): JsonResponse
    {
        $epaper = $this->resolvePublished($locale, $issue);

        if (! app(EpaperAccessPolicy::class)->canView($request->user(), $epaper)) {
            abort_if($epaper->access_level === EpaperAccessLevel::Private, 404);
            abort(403);
        }

        $query = trim((string) $request->validated('q'));
        $perPage = (int) ($request->validated('per_page') ?: EpaperPageSearch::PER_PAGE);

        // نصّ قابل للبحث متوفّر فقط حين تكون طبقة النصّ حاضرة/جزئية (OCR منجَز).
        $searchable = in_array($epaper->text_layer, [EpaperTextLayer::Present, EpaperTextLayer::Partial], true);

        if (! $searchable) {
            return $this->respond($query, $epaper->id, false, []);
        }

        $paginator = EpaperPageSearch::run($epaper, $query, $perPage);

        return $this->respond(
            $query,
            $epaper->id,
            true,
            EpaperSearchResultResource::collection($paginator->getCollection())->resolve($request),
            [
                'total' => $paginator->total(),
                'count' => $paginator->count(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
            ],
        );
    }

    /**
     * @param  array<int,array<string,mixed>>  $results
     * @param  array<string,int>|null  $pagination
     */
    private function respond(string $query, int $issueId, bool $searchable, array $results, ?array $pagination = null): JsonResponse
    {
        $pagination ??= [
            'total' => 0,
            'count' => 0,
            'per_page' => EpaperPageSearch::PER_PAGE,
            'current_page' => 1,
            'total_pages' => 0,
        ];

        return response()->json([
            'data' => [
                'query' => $query,
                'issue_id' => $issueId,
                'searchable' => $searchable,
                'total' => $pagination['total'],
                'results' => $results,
            ],
            'meta' => ['pagination' => $pagination],
        ]);
    }

    private function resolvePublished(string $locale, string $issue): Epaper
    {
        $epaper = Epaper::query()
            ->published()
            ->forLocale($locale)
            ->whereKey((int) $issue)
            ->first();

        abort_if($epaper === null, 404);

        return $epaper;
    }
}
