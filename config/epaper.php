<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | OCR — استخراج نصّ العدد (Phase 4a)
    |--------------------------------------------------------------------------
    | المسار الافتراضيّ يفضّل استخراج النصّ المضمَّن في الـ PDF (pdftotext) — تكلفة
    | صفرية. مزوّد Google Document AI اختياريّ يُفعَّل بيئياً للوثائق الممسوحة ضوئياً.
    | الأسرار (اعتماد حساب الخدمة) عبر env/مدير أسرار — لا تُخزَّن في قاعدة البيانات.
    */

    'ocr' => [
        // طابور المعالجة (ثقيل نسبياً — يُبعَد عن الطابور الافتراضي).
        'queue' => env('EPAPER_OCR_QUEUE', 'media'),

        // المستخرِج المضمَّن (poppler pdftotext) — الافتراضيّ، بلا تكلفة.
        'embedded' => [
            'binary' => env('EPAPER_OCR_PDFTOTEXT', 'pdftotext'),
            'timeout' => (int) env('EPAPER_OCR_EMBEDDED_TIMEOUT', 120),
        ],

        // مزوّد Google Document AI — تصعيد اختياريّ حين يخلو الـ PDF من نصّ مضمَّن.
        'google' => [
            'enabled' => (bool) env('EPAPER_OCR_GOOGLE_ENABLED', false),
            'project_id' => env('EPAPER_OCR_GOOGLE_PROJECT_ID', ''),
            'location' => env('EPAPER_OCR_GOOGLE_LOCATION', 'us'),
            'processor_id' => env('EPAPER_OCR_GOOGLE_PROCESSOR_ID', ''),
            // مسار ملفّ اعتماد حساب الخدمة (JSON) أو محتواه الخام.
            'credentials' => env('EPAPER_OCR_GOOGLE_CREDENTIALS', ''),
            'timeout' => (int) env('EPAPER_OCR_GOOGLE_TIMEOUT', 120),
        ],

        // الأمر المجدوَل/اليدوي لإعادة المعالجة بالدُّفعات.
        'backfill' => [
            'chunk' => (int) env('EPAPER_OCR_BACKFILL_CHUNK', 50),
        ],

        // مراقبة الصحّة: عالق في processing أطول من الحدّ ⇒ فشل (عامل media معطّل)؛
        // عدد الفاشل ≥ العتبة ⇒ تحذير (تراكم يستحق epaper:ocr-backfill).
        'health' => [
            'stuck_minutes' => (int) env('EPAPER_OCR_STUCK_MINUTES', 30),
            'failed_threshold' => (int) env('EPAPER_OCR_FAILED_THRESHOLD', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | بحث الأرشيف العابر (Enterprise — Meilisearch على مستوى الصفحة)
    |--------------------------------------------------------------------------
    | يُفعَّل تلقائياً حين يكون SCOUT_DRIVER=meilisearch. مزامنة الفهرس تُطابَر على
    | طابور «search». عند تعذّر المحرّك في الإنتاج لا نُرهق قاعدة البيانات بمسحٍ على
    | ملايين الصفحات افتراضياً (db_fallback=false ⇒ نتيجة «متدهورة» لطيفة + إنذار)؛
    | يُفعَّل الارتداد للقاعدة فقط في النشرات الصغيرة/الاختبار.
    */

    'search' => [
        'queue' => env('EPAPER_SEARCH_QUEUE', 'search'),
        // عدد نتائج الصفحة الواحدة في الأرشيف (حدّ أعلى مفروض في الطلب أيضاً).
        'per_page' => (int) env('EPAPER_SEARCH_PER_PAGE', 20),
        'max_per_page' => (int) env('EPAPER_SEARCH_MAX_PER_PAGE', 50),
        // طول مقتطف الاقتطاع (كلمات) الذي يُنتجه المحرّك حول التطابق.
        'crop_length' => (int) env('EPAPER_SEARCH_CROP_LENGTH', 40),
        // ارتداد إلى بحث قاعدة البيانات عند تعذّر المحرّك (آمِن للنشرات الصغيرة فقط).
        'db_fallback' => (bool) env('EPAPER_SEARCH_DB_FALLBACK', false),
        // مراقبة الصحّة: أدنى عدد وثائق متوقَّع قبل اعتبار الفهرس «فارغاً/معطوباً».
        'health_min_documents' => (int) env('EPAPER_SEARCH_HEALTH_MIN_DOCS', 1),
        // خنق بحث الأرشيف لكل عميل/دقيقة (أضيق من القراءة العامّة — استعلام محرّك أثقل).
        'rate_limit' => (int) env('EPAPER_SEARCH_RATE_LIMIT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | تحليلات القراءة (Phase 5) — عزل الطابور
    |--------------------------------------------------------------------------
    | بيكون نهاية الجلسة عالي الحجم ومنخفض الأولويّة؛ يُعزَل على طابور «analytics»
    | فلا يُزاحم المهامّ التفاعليّة على الطابور الافتراضيّ. يتطلّب عاملاً لطابور
    | analytics (ضمن تصنيف طوابير المنصّة).
    */

    'analytics' => [
        'queue' => env('EPAPER_ANALYTICS_QUEUE', 'analytics'),
    ],

    /*
    |--------------------------------------------------------------------------
    | توليد الغلاف (من الصفحة الأولى عبر poppler pdftoppm)
    |--------------------------------------------------------------------------
    | يعيد استخدام نظام مشتقّات الوسائط (conversions['cover']). الأداة من حزمة
    | poppler-utils نفسها التي توفّر pdftotext المستعمَل في OCR. يُعزَل على طابور
    | الوسائط. ⚠️ يتطلّب توافر pdftoppm على بيئة التشغيل.
    */
    'cover' => [
        'queue' => env('EPAPER_COVER_QUEUE', 'media'),
        'pdftoppm' => env('EPAPER_COVER_PDFTOPPM', 'pdftoppm'),
        'dpi' => (int) env('EPAPER_COVER_DPI', 150),
        'timeout' => (int) env('EPAPER_COVER_TIMEOUT', 120),
    ],

];
