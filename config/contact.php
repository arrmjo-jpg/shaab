<?php

declare(strict_types=1);

return [

    // ─── إرسال «اتصل بنا» (نموذج عامّ) — حارس إساءة/سبام ثنائيّ الطبقة ─────
    // يُقرَأ من حدّ public.contact في AppServiceProvider (RateLimiter الحاليّ) — لا حارس جديد.
    'submit' => [
        // حدّ لكلّ عميل (X-Client-Id ثمّ IP) لكلّ دقيقة.
        'rate_limit' => (int) env('CONTACT_SUBMIT_RATE_LIMIT', 5),

        // سقف صارم لكلّ IP يكمّل حدّ العميل (الطبقة الثانية) — حارس ضدّ تدوير X-Client-Id.
        // 0 = مُعطَّل (الافتراض الآمن). لا تُفعِّله إلا بعد ضبط TRUSTED_PROXIES، وإلا انهارت
        // كلّ الطلبات إلى عنوان الحافة فخُنِق الإرسال الشرعيّ. انظر advertising.serve.per_ip_rate_limit.
        'per_ip_rate_limit' => (int) env('CONTACT_SUBMIT_PER_IP_RATE_LIMIT', 0),
    ],

];
