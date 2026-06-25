<?php

declare(strict_types=1);

return [

    // ─── إرسال «طلب إعلان» (نموذج عامّ) — حارس إساءة/سبام ثنائيّ الطبقة ────
    // يُقرَأ من حدّ public.ad-request في AppServiceProvider (RateLimiter الحاليّ) — لا حارس جديد.
    'submit' => [
        // حدّ لكلّ عميل (X-Client-Id ثمّ IP) لكلّ دقيقة.
        'rate_limit' => (int) env('AD_REQUEST_SUBMIT_RATE_LIMIT', 5),

        // سقف صارم لكلّ IP يكمّل حدّ العميل (الطبقة الثانية) — حارس ضدّ تدوير X-Client-Id.
        // 0 = مُعطَّل (الافتراض الآمن). لا تُفعِّله إلا بعد ضبط TRUSTED_PROXIES. انظر
        // advertising.serve.per_ip_rate_limit للتحذير الكامل.
        'per_ip_rate_limit' => (int) env('AD_REQUEST_SUBMIT_PER_IP_RATE_LIMIT', 0),
    ],

    // ─── المرفق (إعلان HTML = ZIP) — سقف حجم الرفع للزائر العامّ ──────────
    'attachment' => [
        'zip_max_kb' => (int) env('AD_REQUEST_ZIP_MAX_KB', 20480), // 20MB
    ],

];
