<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Settings\NewspaperSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بوابة وحدة الجريدة الرقمية — دلالة الوحدات المؤسسية: "معطَّل = غير موجود".
 * حين تكون NewspaperSettings.enabled = false تُحجب كل مسارات الجريدة (إدارةً وعموماً)
 * بـ 404 (يصيّرها ApiExceptionRenderer كظرف JSON موحّد للـ API، وكصفحة 404 للعموم) —
 * دون تسريب وجود الوحدة. صفحة إعدادات التفعيل لا تمرّ بهذه البوابة (لتبقى متاحة للتفعيل).
 */
class EnsureNewspaperEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(NewspaperSettings::class)->enabled) {
            abort(404);
        }

        return $next($request);
    }
}
