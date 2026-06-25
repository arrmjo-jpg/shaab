<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Video Library — operational config (non-secret)
|--------------------------------------------------------------------------
*/

return [
    /*
    | قائمة المضيفات المسموح بها لروابط MP4 المباشرة — allow-list صارمة (إضافةً
    | إلى حارس SafeUrl ضدّ المضيفات الخاصّة/non-https). تُطابَق بالمضيف نفسه أو
    | كنطاق فرعي (suffix). فارغة ⇒ تُرفَض كل روابط MP4 المباشرة (افتراضي آمن:
    | لا يُسمح بـ direct_mp4 حتى يُصرّح المشغّل بمضيفاته، مثل نطاق R2/CDN).
    | تُضبط بيئياً عبر VIDEO_MP4_ALLOWED_HOSTS (مفصولة بفواصل).
    */
    'mp4_allowed_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('VIDEO_MP4_ALLOWED_HOSTS', ''))
    ))),
];
