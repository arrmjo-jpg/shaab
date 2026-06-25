<?php

declare(strict_types=1);

return [
    // اسم اتصال قاعدة بيانات المصدر (يُسجَّل ديناميكياً وقت التشغيل من إعدادات
    // التشغيلة — ليس في .env لأن بيانات الاعتماد تُدخَل عبر الواجهة).
    'connection' => 'wp_source',

    // طابور مهام الترحيل (على اتصال الطابور الافتراضي للتطبيق).
    'queue' => env('WP_MIGRATION_QUEUE', 'migration'),

    // حجم الدفعة عند تعداد/توزيع العناصر (لا حلقات عملاقة على 84k+ منشور).
    'chunk' => (int) env('WP_MIGRATION_CHUNK', 200),

    // محاولات إعادة تنفيذ العنصر قبل وسمه فاشلاً (dead-letter).
    'item_tries' => (int) env('WP_MIGRATION_ITEM_TRIES', 3),

    // عنصر عالق بحالة processing أقدم من هذا (دقائق) يُعاد استرداده عند الاستئناف.
    'stale_lock_minutes' => (int) env('WP_MIGRATION_STALE_LOCK_MINUTES', 15),

    // ⚠️ TEMPORARY FEATURE
    // Quick Incremental Import
    // Remove before Production release
    // TODO(production): احذفه أو عطّله. false ⇒ يُخفى الزرّ + يُرفض الـAPI (403) + يعود التدفّق الرسميّ.
    'quick_incremental' => (bool) env('WP_MIGRATION_QUICK_INCREMENTAL', true),

    // الاسم القانوني للكاتب الذي تُسنَد إليه كل المحتوى المُرحَّل (غير قابل للتفاوض).
    // لا تُرحَّل حسابات/كتّاب ووردبريس إطلاقاً — التنظيف التحريري لاحق يدوي.
    'author_name' => env('WP_MIGRATION_AUTHOR_NAME', 'كتاب الموقع'),

    // ضوابط الوسائط أثناء الترحيل — حدود حتمية + أمان شبكة (قواعد #3/#4/#5).
    'media' => [
        'max_bytes' => (int) env('WP_MIGRATION_MEDIA_MAX_BYTES', 26214400),   // 25 ميبي
        'per_post_max' => (int) env('WP_MIGRATION_MEDIA_PER_POST_MAX', 40),   // حدّ التشعّب لكل منشور
        'fetch_timeout' => (int) env('WP_MIGRATION_FETCH_TIMEOUT', 10),       // ثوانٍ
        'fetch_retries' => (int) env('WP_MIGRATION_FETCH_RETRIES', 2),
        'fetch_max_redirects' => (int) env('WP_MIGRATION_FETCH_MAX_REDIRECTS', 2),
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'],
    ],
];
