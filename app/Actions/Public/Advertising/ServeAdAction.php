<?php

declare(strict_types=1);

namespace App\Actions\Public\Advertising;

use App\Enums\AdCreativeType;
use App\Enums\AdDeviceClass;
use App\Models\AdCreative;
use App\Models\AdPlacement;
use App\Models\AdZone;
use App\Support\Advertising\AdBeaconToken;
use App\Support\Advertising\AdBucket;
use App\Support\Advertising\AdServer;
use App\Support\Advertising\AdUrlSafety;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * نقطة العرض العامة (GET /ads/serve/{zoneKey}). الاختيار في الخادم حتميّ ضمن الدلو
 * الزمني الحالي (مُكاش على الحافة بنافذة الدلو). لا تحتسب انطباعاً — الانطباع يؤكَّد
 * بمنارة العميل (served != rendered). تُعيد: الإبداع المختار + بيانات العرض + رمز
 * منارة الانطباع + رابط نقرة موقّع (إن كان للإبداع وجهة آمنة).
 *
 * التجزئة (zone, locale, device) عبر معاملات الاستعلام ⇒ مفتاح كاش الحافة يميّزها تلقائياً.
 */
final class ServeAdAction
{
    public function handle(string $zoneKey, Request $request): JsonResponse
    {
        $locale = $this->resolveLocale($request);
        $device = AdDeviceClass::fromString($this->queryString($request, 'device'))->value;
        $bucket = AdBucket::current();

        $candidate = AdServer::serve($zoneKey, $locale, $device, $bucket);
        $ad = $candidate !== null ? $this->buildAd((int) $candidate['placement_id'], $bucket) : null;

        $window = AdBucket::window();

        // لا stale-while-revalidate (V4): تقديم نسخة قديمة بعد max-age قد يُرجِع استجابةً
        // رمزُها خرج من نافذة الدلو (±1) فيُرفَض انطباعها ⇒ احتساب ناقص. نحصر عمر النسخة
        // المُقدَّمة بـ max-age (= نافذة الدلو) ليبقى الرمز ضمن نافذة قبوله.
        return ApiResponse::success(
            data: ['zone' => $zoneKey, 'bucket' => $bucket, 'ad' => $ad],
            meta: ['expires_in' => $window],
        )->header('Cache-Control', sprintf(
            'public, max-age=%d, s-maxage=%d',
            $window,
            $window,
        ));
    }

    /** @return array<string,mixed>|null */
    private function buildAd(int $placementId, int $bucket): ?array
    {
        // البِركة مُكاشة (حتى pool_ttl)؛ نُعيد التحقّق من الحياة وقت العرض ونحلّ بيانات العرض.
        $placement = AdPlacement::query()
            ->whereKey($placementId)
            ->where('is_active', true)
            ->with([
                'creative' => fn ($q) => $q->where('is_active', true),
                'creative.campaign',
                'creative.mediaAsset',
                'zone:id,width,height',
            ])
            ->first(['id', 'ad_creative_id', 'ad_zone_id']);

        $creative = $placement?->creative;
        if ($placement === null || $creative === null) {
            return null; // عُطِّل/حُذِف بعد بناء البِركة — تدهور رشيق (لا إعلان).
        }

        // إعادة التحقّق من أهليّة الحملة وقت العرض (V3): النافذة الزمنية تُقيَّم على الساعة
        // الحاليّة لا على لحظة بناء البِركة المُكاشة — فلا تُعرض حملة انتهت/لم تبدأ نافذتها
        // (أو صارت غير نشطة/محذوفة soft) خلال عمر البِركة (≤ pool_ttl).
        if ($creative->campaign === null || ! $creative->campaign->isServable()) {
            return null;
        }

        $render = $this->render($creative);
        if ($render === null) {
            return null; // وسيط مفقود لإبداع صورة — لا نعرض إعلاناً مكسوراً.
        }

        $token = AdBeaconToken::issue((int) $placement->id, (int) $placement->ad_zone_id, $bucket);

        $ad = [
            'placement_id' => (int) $placement->id,
            'creative_id' => (int) $creative->id,
            'type' => $creative->type->value,
            'width' => $placement->zone?->width,
            'height' => $placement->zone?->height,
            'render' => $render,
            'impression' => [
                'token' => $token,
                'url' => url('/api/v1/ads/track/impression'),
            ],
        ];

        // النقرة موقّعة وتُحلّ وجهتها في الخادم (لا open redirect) — تُتاح فقط حين وجود وجهة آمنة.
        if (AdUrlSafety::isSafe($creative->landing_url)) {
            $ad['click'] = [
                'token' => $token,
                'url' => url('/api/v1/ads/click/'.$token),
            ];
        }

        return $ad;
    }

    /** @return array<string,mixed>|null */
    private function render(AdCreative $creative): ?array
    {
        return match ($creative->type) {
            AdCreativeType::Image => $this->imageRender($creative),
            AdCreativeType::Html => ['html' => (string) $creative->html_code],
            default => null, // video غير مُفعّل في هذه المرحلة
        };
    }

    /** @return array<string,mixed>|null */
    private function imageRender(AdCreative $creative): ?array
    {
        $url = $creative->mediaAsset?->url();
        if ($url === null || $url === '') {
            return null;
        }

        return [
            'image_url' => $url,
            'alt' => (string) $creative->alt_text,
        ];
    }

    private function resolveLocale(Request $request): string
    {
        $locale = $this->queryString($request, 'locale');

        return $locale !== null && in_array($locale, AdZone::LOCALES, true) ? $locale : 'ar';
    }

    /** معامل استعلام نصّيّ آمن (يتجاهل المصفوفات/القيم غير النصّية). */
    private function queryString(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : null;
    }
}
