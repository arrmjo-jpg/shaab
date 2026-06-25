<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Settings\ThirdPartySettings;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إعداد reCAPTCHA العام (بدون مصادقة) — لعرض الـ widget قبل الدخول.
 * يُرجع المفتاح العام فقط — لا أسرار إطلاقاً.
 */
class RecaptchaConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $s = app(ThirdPartySettings::class);

        return ApiResponse::success(data: [
            'enabled' => (bool) $s->recaptcha_enabled,
            'version' => $s->recaptcha_version,
            'site_key' => $s->recaptcha_site_key,
        ]);
    }
}
