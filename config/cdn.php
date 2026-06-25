<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CDN / Media Delivery
|--------------------------------------------------------------------------
| نطاق توصيل الأصول والوسائط (Cloudflare CDN / R2).
| منفصل عن config/frontend.php — هذا concern توصيل، ذاك concern تطبيق SPA.
*/

return [
    // نطاق CDN العام للأصول الثابتة
    'url' => env('CDN_URL', env('APP_URL', 'http://localhost')),

    // نطاق الوسائط (الصور/الملفات المرفوعة) — يفترض R2 خلف CDN
    'media_url' => env('MEDIA_URL', env('CDN_URL', env('APP_URL', 'http://localhost'))),

    // تفعيل إعادة كتابة روابط الوسائط لتمر عبر الـ CDN
    'enabled' => (bool) env('CDN_ENABLED', false),

    // ─── Cloudflare API (وحدة CDN) ──────────────────────────────────────
    'api' => [
        'base_url' => env('CLOUDFLARE_API_BASE', 'https://api.cloudflare.com/client/v4'),
        'timeout' => (int) env('CLOUDFLARE_API_TIMEOUT', 8),
        // الحد الأقصى لعدد الروابط في طلب purge واحد (قيد Cloudflare)
        'purge_chunk' => 30,
    ],

    // تحديد معدّل نداء Cloudflare API (حماية آمنة)
    'rate_limit' => [
        'max' => (int) env('CLOUDFLARE_RATE_MAX', 1000),
        'window' => (int) env('CLOUDFLARE_RATE_WINDOW', 300),
    ],

    // إعادة المحاولة الذكية عند الأعطال العابرة
    'retry' => [
        'max_attempts' => (int) env('CLOUDFLARE_RETRY_ATTEMPTS', 3),
        'base_ms' => (int) env('CLOUDFLARE_RETRY_BASE_MS', 200),
        'cap_ms' => (int) env('CLOUDFLARE_RETRY_CAP_MS', 2000),
    ],
];
