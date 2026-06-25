<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| AI Editorial Copilot — Operational defaults
|--------------------------------------------------------------------------
| المفاتيح والنماذج واختيار المزوّد (openai|gemini) تُدار من لوحة الإدارة عبر
| ThirdPartySettings (group: third_party) — لا تُكرَّر هنا ولا تُقرأ من .env.
| هذا الملف يحمل فقط الإعدادات التشغيلية غير السرّية (حدّ المعدّل + افتراضيات
| الشبكة التي لا تظهر في اللوحة).
*/

return [
    // حدّ معدّل الطلبات لكل مستخدم/دقيقة (حماية من الإساءة + التكلفة)
    'rate_limit' => (int) env('AI_RATE_LIMIT', 20),

    // نقطة نهاية Gemini الافتراضية (لا تُخزَّن في اللوحة)
    'gemini_base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

    /*
    | حدود تكلفة/استخدام الذكاء الاصطناعي — أمان إنتاجي (0 = بلا حدّ).
    | عند التجاوز: الميزات الهجينة تسقط لبدائل حتمية، والميزات التي تتطلّب ذكاءً
    | ترفض بلطف (429). تُضبط بيئياً (env)؛ مرئية للمشغّل عبر /admin/ai/usage.
    */
    'caps' => [
        'daily_requests' => (int) env('AI_DAILY_REQUEST_CAP', 0),
        'monthly_requests' => (int) env('AI_MONTHLY_REQUEST_CAP', 0),
        'user_daily_requests' => (int) env('AI_USER_DAILY_REQUEST_CAP', 0),
        'monthly_budget_usd' => (float) env('AI_MONTHLY_BUDGET_USD', 0),
    ],

    /*
    | تكلفة تقديرية لكل 1000 توكِن (USD) لكل مزوّد — لتقدير الإنفاق فقط (ليست
    | فوترة دقيقة). التوكِنات تُقدَّر من حجم الإدخال/الإخراج (~4 أحرف/توكِن).
    */
    'cost_per_1k_tokens' => [
        'openai' => (float) env('AI_COST_OPENAI_PER_1K', 0.005),
        'gemini' => (float) env('AI_COST_GEMINI_PER_1K', 0.0005),
    ],
];
