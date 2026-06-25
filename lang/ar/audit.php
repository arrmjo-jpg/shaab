<?php

declare(strict_types=1);

return [
    'event' => [
        'created' => 'إنشاء',
        'updated' => 'تعديل',
        'deleted' => 'حذف',
        'restored' => 'استرجاع',
    ],
    'settings_updated' => 'تحديث الإعدادات (:group)',
    'rbac' => [
        'user_roles_updated' => 'تعديل أدوار المستخدم',
        'role_permissions_updated' => 'تعديل صلاحيات الدور',
    ],
    'media' => [
        'attached' => 'إسناد :count أصل وسائط',
        'detached' => 'فصل :count أصل وسائط',
        'replaced' => 'استبدال وسائط :slot',
    ],
    'broadcast' => [
        'viewer_kicked' => 'طرد مشاهد من البثّ',
        'viewer_banned' => 'حظر مشاهد مؤقّتاً',
        'viewer_unbanned' => 'رفع حظر مشاهد',
        'audience_closed' => 'إغلاق جمهور البثّ',
        'audience_reopened' => 'إعادة فتح جمهور البثّ',
        'emergency_shutdown' => 'إيقاف طارئ للبثّ',
    ],
];
