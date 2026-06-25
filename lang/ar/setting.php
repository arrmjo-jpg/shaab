<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| رسائل وحدة الإعدادات
|--------------------------------------------------------------------------
*/

return [
    'group_not_found' => 'مجموعة الإعدادات المطلوبة غير موجودة.',

    'updated' => 'تم تحديث الإعدادات بنجاح.',
    'branding_uploaded' => 'تم رفع ملفات العلامة بنجاح.',

    'firebase_uploaded' => 'تم رفع ملف اعتماد Firebase بنجاح.',
    'firebase_invalid_json' => 'ملف اعتماد Firebase غير صالح أو ينقصه معرّف المشروع.',

    'mail_test_success' => 'تم إرسال رسالة الاختبار بنجاح. إعدادات البريد صحيحة.',
    'mail_test_failed' => 'فشل الاتصال بخادم البريد. تحقّق من الإعدادات.',
    'mail_test_subject' => 'رسالة اختبار من AlphaCMS',
    'mail_test_body' => 'هذه رسالة اختبار للتأكد من صحة إعدادات خادم البريد.',

    'cdn_test_success' => 'تم التحقق من اتصال Cloudflare بنجاح.',
    'cdn_test_failed' => 'فشل التحقق من اتصال Cloudflare. تحقّق من الرمز.',
    'cdn_token_missing' => 'لم يتم ضبط رمز Cloudflare بعد.',

    'integration_key_missing' => 'لم يتم ضبط مفتاح الـ API بعد.',
    'sportmonks_test_success' => 'تم التحقق من اتصال SportMonks بنجاح.',
    'sportmonks_test_failed' => 'فشل التحقق من اتصال SportMonks. تحقّق من المفتاح.',
    'openweather_test_success' => 'تم التحقق من اتصال OpenWeather بنجاح.',
    'openweather_test_failed' => 'فشل التحقق من اتصال OpenWeather. تحقّق من المفتاح.',
    'whatsapp_test_success' => 'تم التحقق من اتصال واتساب (UltraMsg) بنجاح.',
    'whatsapp_test_failed' => 'فشل التحقق من اتصال واتساب. تحقّق من الـ Instance والرمز.',

    'media_test_success' => 'تم التحقق من اتصال التخزين البعيد بنجاح.',
    'media_test_failed' => 'فشل الاتصال بالتخزين البعيد. تحقّق من الاعتماديات.',
    'media_test_missing' => 'الاعتماديات ناقصة (المفتاح/السرّ/المستودع مطلوبة).',
    'media_remote_disabled' => 'التخزين البعيد غير مُفعَّل. فعّله أولاً قبل المزامنة.',
    'media_sync_started' => 'بدأت مزامنة الوسائط غير المتزامنة في الخلفية.',
    'media_endpoint_unsafe' => 'نقطة النهاية يجب أن تكون https على مضيف عام (لا مضيفات داخلية/خاصّة).',
];
