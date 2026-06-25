<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Performance Knobs
|--------------------------------------------------------------------------
| المصدر الموحّد لقواعد الأداء — تتبنّاه كل الوحدات القادمة.
| ملاحظة: بادئة الكاش تعيش في config/cache.php (CACHE_PREFIX) — لا تُكرَّر هنا.
*/

return [

    'pagination' => [
        'default' => (int) env('PAGINATION_DEFAULT', 15),
        'max' => (int) env('PAGINATION_MAX', 100),
        'cursor_threshold' => (int) env('PAGINATION_CURSOR_THRESHOLD', 10000),
    ],

    // سقف معدّل قراءة المسارات العامة لكل عميل/دقيقة (حارس إساءة/DoS على الأصل).
    // سخيّ عمداً — الـ CDN يمتصّ معظم القراءات؛ هذا حدّ الأصل للعملاء المباشرين.
    // ملاحظة تشغيلية: خلف CDN يجب ضبط TrustProxies كي يحلّ $request->ip() عنوان
    // العميل الحقيقي بدل عقدة الحافة (وإلا قد يُخنَق ملء الـ CDN عند الذروة).
    'public_read_rate_limit' => (int) env('PERFORMANCE_PUBLIC_READ_RATE_LIMIT', 120),

    // حدود رفع الوسائط ومعالجتها (تصلّب الرفع — Phase 4). كلها قابلة للضبط بيئياً.
    'media' => [
        // سقف حجم الفيديو العام (KB) — حارس إساءة التخزين عند الرفع.
        'video_max_kb' => (int) env('MEDIA_VIDEO_MAX_KB', 256000),
        // سقف أضيق لفيديو الريل (أقصر بطبيعته) — 150MB.
        'reel_video_max_kb' => (int) env('MEDIA_REEL_VIDEO_MAX_KB', 153600),
        // سقف حجم الصورة (KB) + أقصى بُعد بكسل (حارس صور عملاقة).
        'image_max_kb' => (int) env('MEDIA_IMAGE_MAX_KB', 5120),
        'image_max_dimension' => (int) env('MEDIA_IMAGE_MAX_DIMENSION', 8000),
        // حدود ما بعد الـ probe (تُفرَض في وظيفة الترميز): مدّة وأبعاد الفيديو.
        'video_max_duration' => (int) env('MEDIA_VIDEO_MAX_DURATION', 600),  // 10 دقائق
        'reel_max_duration' => (int) env('MEDIA_REEL_MAX_DURATION', 180),    // 3 دقائق
        'video_max_dimension' => (int) env('MEDIA_VIDEO_MAX_DIMENSION', 8192),
        // عتبات صحّة المعالجة (Phase 5 — مراقبة): أصل عالق في processing أطول من
        // هذا = عامل معطّل/مهمة معلّقة (فشل صحّي)؛ وعدد الفشل خلال 24س فوق العتبة
        // = تحذير صحّي.
        'stuck_processing_minutes' => (int) env('MEDIA_STUCK_PROCESSING_MINUTES', 60),
        'failed_alert_threshold' => (int) env('MEDIA_FAILED_ALERT_THRESHOLD', 10),
        // مهلة تنظيف الأصول المرحّلة المهجورة (غير مُسنَدة) — attach-on-save
        'orphan_ttl_hours' => (int) env('MEDIA_ORPHAN_TTL_HOURS', 48),
    ],

    // تجميع المشاهدات (مكافحة تنازع الصفّ الساخن): تُجمَّع الزيادات في مخزن مؤقّت
    // (Redis) وتُفرَّغ دورياً عبر engagement:flush-views بدل UPDATE متزامن لكل مشاهدة.
    // مفعّل افتراضياً (الإنتاج يشغّل المُجدوِل)؛ الاختبارات تعطّله لاحتساب فوري حتمي.
    'view_buffer' => [
        'enabled' => (bool) env('ENGAGEMENT_BUFFER_VIEWS', true),
    ],

    // منارة المشاهدة (uncached): نقطة مستقلّة عن الكاش لاحتساب دقيق خلف الـ CDN.
    // ttl: عمر رمز المنارة الموقّع (ثوانٍ) — يغطّي نافذة كاش الحافة + تأخّر العميل.
    // rate_limit: سقف نبضات المنارة لكل عميل/دقيقة (مقاومة إساءة التضخيم).
    'view_beacon' => [
        'ttl' => (int) env('ENGAGEMENT_BEACON_TTL', 900),
        'rate_limit' => (int) env('ENGAGEMENT_BEACON_RATE_LIMIT', 120),
    ],

    // مرآة قابلة للضبط لقيم App\Support\Cache\CacheTtl (بالثواني)
    'cache' => [
        'settings_ttl' => (int) env('CACHE_SETTINGS_TTL', 86400),
        'short_ttl' => (int) env('CACHE_SHORT_TTL', 300),
        'medium_ttl' => (int) env('CACHE_MEDIUM_TTL', 1800),
        'long_ttl' => (int) env('CACHE_LONG_TTL', 21600),
    ],

    // أسماء الطوابير المنطقية — تُمرَّر عبر ->onQueue(config('performance.queues.x'))
    'queues' => [
        'default' => 'default',
        'media' => 'media',
        'search' => 'search',
        'notifications' => 'notifications',
        'analytics' => 'analytics',
        'ai' => 'ai',
    ],
];
