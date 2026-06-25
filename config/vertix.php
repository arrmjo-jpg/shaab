<?php

declare(strict_types=1);

return [
    // اسم اتصال قاعدة مصدر Vertix (مُعرَّف في config/database.php).
    'connection' => env('VERTIX_CONNECTION', 'vertix'),

    // طابور مهام ترحيل Vertix (معزول، مستقلّ عن طابور WordPress).
    'queue' => env('VERTIX_QUEUE', 'vertix'),

    // حجم الدفعة عند معالجة الأخبار (تعداد ~221k — لا حلقات عملاقة).
    'chunk' => (int) env('VERTIX_CHUNK', 500),

    // الكاتب القانونيّ المُسنَد لكلّ المحتوى المُرحَّل من Vertix (يُنشأ مرّة).
    'author_name' => env('VERTIX_AUTHOR_NAME', 'القلعة نيوز'),

    // لغة المحتوى الهدف في AlphaCMS (مصدر Vertix lang='arb').
    'locale' => env('VERTIX_LOCALE', 'ar'),

    // توليد رابط الصورة: base + '/' + folder + '/images/' + ph_name (لا يُخزَّن الرابط).
    'cdn_base' => rtrim((string) env('VERTIX_CDN_BASE', 'https://cdn.alqalahnews.net'), '/'),
    'image_segment' => env('VERTIX_IMAGE_SEGMENT', 'images'),

    // تنزيل صور Vertix إلى مكتبة MediaAsset (Option C — مستقلّ عن WpMediaImporter).
    // يعيد استخدام القطع المحايدة: SafeUrl (SSRF) + Http + StoreMediaAssetAction
    // (تخزين + ديدوب SHA-256 + توليد المصغّرات). فشل التنزيل ⇒ بلا غلاف (لا يُسقط الخبر).
    'media' => [
        'fetch_timeout' => (int) env('VERTIX_MEDIA_TIMEOUT', 10),
        'fetch_retries' => (int) env('VERTIX_MEDIA_RETRIES', 2),
        'fetch_max_redirects' => (int) env('VERTIX_MEDIA_MAX_REDIRECTS', 2),
        'max_bytes' => (int) env('VERTIX_MEDIA_MAX_BYTES', 26214400), // 25MB
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'],
    ],
];
