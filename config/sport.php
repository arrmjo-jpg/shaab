<?php

declare(strict_types=1);

/**
 * إعدادات تكامل الرياضة (365) خادميًّا — مصدر المواعيد لنظام إشعارات «تابع» (المرحلة 2).
 * القيم قابلة للتجاوز عبر env دون تعديل كود.
 */
return [
    // قاعدة API العامّة لـ365 (نفس مصدر الواجهة).
    'api_base' => env('SPORT_365_BASE', 'https://webws.365scores.com/web'),

    // معاملات مشتركة ثابتة (نوع التطبيق/اللغة/التوقيت/الدولة) — كنمط الواجهة.
    'api_common' => 'appTypeId=3&langId=27&timezoneName=Asia/Amman&userCountryId=6',

    // مهلة طلب 365 (ثوانٍ).
    'http_timeout' => env('SPORT_365_TIMEOUT', 8),

    // نافذة تذكير ما قبل المباراة (دقائق قبل الانطلاق) — تُستعمل في الكتلة B.
    'reminder_lead_minutes' => env('SPORT_REMINDER_LEAD_MINUTES', 30),
];
