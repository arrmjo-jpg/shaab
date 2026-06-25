<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Auth;

use App\Http\Controllers\Controller;
use App\Settings\ThirdPartySettings;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إعداد الدخول الاجتماعيّ العام (بدون مصادقة) — لعرض أزرار المزوّدات المفعّلة في صفحة الدخول.
 * يُرجع فقط المزوّدات المفعّلة + رابط بدء التدفّق (لا أسرار، لا client_secret).
 */
class SocialAuthConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $settings = app(ThirdPartySettings::class);

        $providers = [];

        if ($settings->google_enabled) {
            $providers[] = ['id' => 'google', 'redirect_url' => url('/api/v1/auth/social/google/redirect')];
        }

        if ($settings->facebook_enabled) {
            $providers[] = ['id' => 'facebook', 'redirect_url' => url('/api/v1/auth/social/facebook/redirect')];
        }

        return ApiResponse::success(data: ['providers' => $providers]);
    }
}
