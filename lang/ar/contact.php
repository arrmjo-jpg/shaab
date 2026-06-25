<?php

declare(strict_types=1);

return [
    'type' => [
        'inquiry' => 'استفسار',
        'complaint' => 'شكوى',
        'suggestion' => 'اقتراح',
        'other' => 'أخرى',
    ],
    'status' => [
        'new' => 'جديد',
        'in_review' => 'قيد المراجعة',
        'replied' => 'تم الرد',
        'closed' => 'مغلق',
    ],
    'created' => 'تم استلام رسالتك، وسنتواصل معك في أقرب وقت.',
    'status_changed' => 'تم تحديث الحالة.',
    'deleted' => 'تم حذف الرسالة.',
    'replied' => 'تم إرسال الرد بنجاح.',
    'reply_mail_failed' => 'تعذّر إرسال البريد، لم يُعتمَد الرد. حاول لاحقاً.',
    'reply_mail' => [
        'subject' => 'رد على رسالتك: :subject',
    ],
    'notification' => [
        'subject' => 'رسالة اتصال جديدة',
        'line' => 'وردت رسالة :type جديدة من :name.',
    ],
];
