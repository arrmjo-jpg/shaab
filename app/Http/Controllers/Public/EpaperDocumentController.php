<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\EpaperAccessLevel;
use App\Enums\EpaperStatus;
use App\Http\Controllers\Controller;
use App\Models\Epaper;
use App\Support\Epaper\EpaperAccessPolicy;
use App\Support\Epaper\EpaperDocumentDelivery;
use App\Support\Epaper\EpaperUsageRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * تسليم وثيقة العدد — لا روابط PDF خام في الصفحة؛ القارئ يطلب رابطاً موقَّتاً من هنا
 * بعد فحص السياسة (canView). التنزيل يُفرَض خادمياً (canDownload) لا بإخفاء الزرّ.
 * البثّ التطبيقيّ احتياطيّ طارئ فقط (موقَّع) حين يتعذّر التخزين البعيد.
 */
class EpaperDocumentController extends Controller
{
    /** يصكّ رابط عرض موقَّت (JSON للقارئ، أو تحويل 302 للفتح بلا جافاسكربت). */
    public function document(Request $request, string $locale, string $issue): Response
    {
        $epaper = $this->resolvePublished($locale, $issue);

        if (! app(EpaperAccessPolicy::class)->canView($request->user(), $epaper)) {
            abort_if($epaper->access_level === EpaperAccessLevel::Private, 404);
            abort(403);
        }

        $minted = app(EpaperDocumentDelivery::class)->viewUrl($epaper);

        if ($request->wantsJson()) {
            return response()->json($minted);
        }

        return redirect($minted['url']); // فتح مباشر بلا جافاسكربت
    }

    /** تنزيل — استحقاق مفروض خادمياً؛ تحويل 302 لرابط موقَّت (60 ث، مرفق). */
    public function download(Request $request, string $locale, string $issue): Response
    {
        $epaper = $this->resolvePublished($locale, $issue);

        if (! app(EpaperAccessPolicy::class)->canDownload($request->user(), $epaper)) {
            abort_if($epaper->access_level === EpaperAccessLevel::Private, 404);
            abort(403);
        }

        EpaperUsageRecorder::recordDownload($epaper->id); // عدّاد تنزيلات واقعيّ (أفضل-جهد)

        return redirect(app(EpaperDocumentDelivery::class)->downloadUrl($epaper)['url']);
    }

    /** بثّ احتياطيّ موقَّع (Range عبر BinaryFileResponse) — يُحقَّق التوقيع عبر middleware('signed'). */
    public function stream(Request $request, Epaper $epaper): Response
    {
        // إعادة فحص النشر فقط (التوقيع القصير يحدّ التقادم؛ لا سياسة كاملة لكل نطاق بايتات).
        abort_unless(
            $epaper->status === EpaperStatus::Published
                && $epaper->published_at !== null
                && ! $epaper->published_at->isFuture(),
            404,
        );

        $asset = $epaper->mediaAsset;
        abort_if($asset === null, 404);

        $disk = Storage::disk($asset->disk);
        abort_unless($disk->exists($asset->path), 404);

        $delivery = app(EpaperDocumentDelivery::class);
        $path = $disk->path($asset->path);

        return $request->query('disposition') === 'attachment'
            ? response()->download($path, $delivery->filename($epaper), ['Content-Type' => 'application/pdf'])
            : response()->file($path, ['Content-Type' => 'application/pdf']);
    }

    private function resolvePublished(string $locale, string $issue): Epaper
    {
        $epaper = Epaper::query()
            ->published()
            ->forLocale($locale)
            ->whereKey((int) $issue)
            ->with('mediaAsset')
            ->first();

        abort_if($epaper === null, 404);

        return $epaper;
    }
}
