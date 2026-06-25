<?php

declare(strict_types=1);

return [
    'followed' => 'تمت المتابعة.',
    'unfollowed' => 'تم إلغاء المتابعة.',
    'unsupported_type' => 'نوع غير مدعوم للمتابعة.',

    // إشعارات «تابع» (المرحلة 2) — عنوان + رسالة لكلّ نوع. :home/:away/:player/:minute/:label.
    'notification' => [
        'match_reminder_title' => 'تذكير مباراة',
        'match_reminder' => 'مباراة تتابعها تبدأ قريبًا: :home ضد :away',
        'match_goal_title' => 'هدف',
        'match_goal' => 'هدف! :player :minute — :home ضد :away',
        'match_yellow_card_title' => 'بطاقة صفراء',
        'match_yellow_card' => 'بطاقة صفراء: :player :minute — :home ضد :away',
        'match_red_card_title' => 'بطاقة حمراء',
        'match_red_card' => 'بطاقة حمراء: :player :minute — :home ضد :away',
        'match_event_title' => 'حدث مباراة',
        'match_event' => ':label :minute — :home ضد :away',
    ],
];
