<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\EpaperAccessLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\EpaperArchiveSearchRequest;
use App\Models\Epaper;
use App\Support\Epaper\EpaperAccessPolicy;
use App\Support\Epaper\EpaperArchiveSearch;
use App\Support\Epaper\EpaperUsageRecorder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;

/**
 * بحث الأرشيف العابر للأعداد — JSON. النطاق المؤسّسيّ مدعوم بـ Meilisearch (عبر
 * EpaperArchiveSearch) مع ارتداد قاعدة البيانات للنشرات الصغيرة/الاختبار. لا يُسرَّب
 * نصّ عددٍ لا يُعرض: الوصول يُفرَض **داخل المحرّك** عبر مستويات مُشتقّة من السياسة هنا
 * (نفس عقد canView). الخنق وبوابة الوحدة (throttle + newspaper.enabled) من المسار.
 * الشكل ثابت بين المحرّك والقاعدة (نفس عقد الاستجابة، لا تغيّر في تجربة الواجهة).
 */
class EpaperArchiveSearchController extends Controller
{
    public function search(EpaperArchiveSearchRequest $request, string $locale): JsonResponse
    {
        app()->setLocale($locale);

        $query = trim((string) $request->validated('q'));
        $perPage = EpaperArchiveSearch::perPage(
            $request->validated('per_page') !== null ? (int) $request->validated('per_page') : null,
        );
        $page = max(1, (int) ($request->validated('page') ?: 1));

        $filters = [
            'locale' => $locale, // الأرشيف مسبوق باللغة — نقصر البحث على لغة المسار
            'issue_number' => $request->validated('issue_number'),
            'date_from' => $request->validated('date_from'),
            'date_to' => $request->validated('date_to'),
        ];

        $result = EpaperArchiveSearch::run($query, $this->viewableLevels($request->user()), $filters, $perPage, $page);

        EpaperUsageRecorder::recordArchiveSearch($locale); // استخدام بحث الأرشيف (أفضل-جهد)

        return response()->json([
            'data' => [
                'query' => $query,
                'locale' => $locale,
                'total' => $result['pagination']['total'],
                'degraded' => $result['degraded'],
                'results' => $result['results'],
            ],
            'meta' => [
                'pagination' => $result['pagination'],
                'engine' => $result['engine'],
            ],
        ]);
    }

    /**
     * مستويات الوصول التي يُسمح بعرضها للمستخدم الحاليّ — تُشتقّ بفحص السياسة على عددٍ
     * عابر لكل مستوى. القرار على «مستوى المستوى» (لا العدد الفرديّ): يُبقي الاستعلام
     * مجموعيّاً وسريعاً، وهو آمن — شامل-أقلّ لا تسريب: حتى لو ربط المضيف سياسة تعتمد على
     * العدد بعينه، أسوأ ما يحدث استبعاد مستوى بأكمله (نقص لا كشف). الافتراضيّة موحّدة
     * بالمستوى فالنتيجة دقيقة لها.
     *
     * @return array<int,string>
     */
    private function viewableLevels(?Authenticatable $user): array
    {
        $policy = app(EpaperAccessPolicy::class);

        return array_values(array_filter(
            EpaperAccessLevel::values(),
            static fn (string $level): bool => $policy->canView($user, new Epaper(['access_level' => $level])),
        ));
    }
}
