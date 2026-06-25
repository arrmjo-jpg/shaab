<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\EpaperAccessLevel;
use App\Http\Controllers\Controller;
use App\Models\Epaper;
use App\Models\EpaperBookmark;
use App\Models\EpaperReadingProgress;
use App\Support\Epaper\EpaperAccessPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * حالة القارئ لكل مستخدم مُصادَق (Phase 5): متابعة القراءة + الإشارات المرجعية.
 * JSON على مسارات الويب (نفس سياق مصادقة الصفحة). الزوّار غير مُصادَقين → 401
 * (الواجهة تستخدم localStorage). يحترم EpaperAccessPolicy تماماً كتسليم الوثيقة.
 */
class EpaperReaderStateController extends Controller
{
    public function show(Request $request, string $locale, string $issue): JsonResponse
    {
        [$epaper, $userId] = $this->context($request, $locale, $issue);

        $progress = EpaperReadingProgress::query()
            ->where('user_id', $userId)->where('epaper_id', $epaper->id)->first();

        $bookmarks = EpaperBookmark::query()
            ->where('user_id', $userId)->where('epaper_id', $epaper->id)
            ->orderBy('page_number')->pluck('page_number')->all();

        return response()->json([
            'last_page' => $progress?->last_page,
            'bookmarks' => $bookmarks,
        ]);
    }

    public function saveProgress(Request $request, string $locale, string $issue): JsonResponse
    {
        [$epaper, $userId] = $this->context($request, $locale, $issue);
        $data = $request->validate(['page' => ['required', 'integer', 'min:1']]);

        EpaperReadingProgress::query()->updateOrCreate(
            ['user_id' => $userId, 'epaper_id' => $epaper->id],
            ['last_page' => (int) $data['page']],
        );

        return response()->json(['saved' => true]);
    }

    public function addBookmark(Request $request, string $locale, string $issue): JsonResponse
    {
        [$epaper, $userId] = $this->context($request, $locale, $issue);
        $data = $request->validate(['page' => ['required', 'integer', 'min:1']]);

        EpaperBookmark::query()->firstOrCreate([
            'user_id' => $userId,
            'epaper_id' => $epaper->id,
            'page_number' => (int) $data['page'],
        ]);

        return response()->json(['bookmarked' => true]);
    }

    public function removeBookmark(Request $request, string $locale, string $issue, string $page): JsonResponse
    {
        [$epaper, $userId] = $this->context($request, $locale, $issue);

        EpaperBookmark::query()
            ->where('user_id', $userId)->where('epaper_id', $epaper->id)
            ->where('page_number', (int) $page)->delete();

        return response()->json(['bookmarked' => false]);
    }

    /**
     * يحلّ العدد المنشور، يفرض canView (خاصّ→404، مشترك→403)، ثمّ يتطلّب مستخدماً
     * مُصادَقاً (زائر→401: لا حالة خادمية، الواجهة تستخدم localStorage).
     *
     * @return array{0:Epaper,1:int}
     */
    private function context(Request $request, string $locale, string $issue): array
    {
        $epaper = Epaper::query()->published()->forLocale($locale)->whereKey((int) $issue)->first();
        abort_if($epaper === null, 404);

        $user = $request->user();
        if (! app(EpaperAccessPolicy::class)->canView($user, $epaper)) {
            abort_if($epaper->access_level === EpaperAccessLevel::Private, 404);
            abort(403);
        }
        abort_if($user === null, 401);

        return [$epaper, (int) $user->id];
    }
}
