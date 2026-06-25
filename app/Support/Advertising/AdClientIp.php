<?php

declare(strict_types=1);

namespace App\Support\Advertising;

use Illuminate\Http\Request;

/**
 * مفتاح IP مُطبَّع لربط حمايات الإعلان (V1): سقف معدّل لكل IP + منع تكرار النقر المرتكز
 * على IP. يُطبّع IPv6 إلى بادئة /64 (العميل قد يملك النطاق كاملاً فلا يدوّر داخله لتجاوز
 * السقف) بينما IPv4 يُؤخذ كاملاً، ثم يُجزّأ الناتج (لا تخزين عنوان خام في مفاتيح الكاش).
 *
 * تحذير تشغيليّ: صحّته تعتمد على ضبط TrustProxies كي يُرجِع $request->ip() عنوان العميل
 * الحقيقي خلف الـ CDN لا عنوان عقدة الحافة. لا تُفعَّل الطبقات المرتكزة على IP
 * (per_ip_rate_limit / strict_click_dedup) قبل ضبط TRUSTED_PROXIES وقفل الأصل على الـ CDN.
 */
final class AdClientIp
{
    public static function key(Request $request): string
    {
        return self::fromIp((string) $request->ip());
    }

    public static function fromIp(string $ip): string
    {
        $packed = @inet_pton($ip);

        if ($packed === false) {
            return hash('sha256', $ip); // تعذّر التحليل — استخدم النصّ كما هو.
        }

        // IPv6 (16 بايت) ⇒ بادئة /64 (أوّل 8 بايت)؛ IPv4 (4 بايت) ⇒ العنوان كامل.
        $prefix = strlen($packed) === 16 ? substr($packed, 0, 8) : $packed;

        return hash('sha256', $prefix);
    }
}
