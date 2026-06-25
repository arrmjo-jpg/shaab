<?php

declare(strict_types=1);

return [
    'status' => [
        'draft' => 'مسودة',
        'submitted' => 'تم الإرسال',
        'in_review' => 'بانتظار المراجعة',
        'scheduled' => 'مجدول',
        'published' => 'منشور',
        'rejected' => 'مرفوض',
        'archived' => 'مؤرشف',
    ],
    'visibility' => [
        'public' => 'عام',
        'unlisted' => 'غير مُدرَج',
        'private' => 'خاص',
    ],
    'source' => [
        'unsupported' => 'مصدر فيديو غير مدعوم. المسموح: يوتيوب، فيميو، أو رابط MP4 مباشر من مضيف مُصرَّح.',
        'not_uploaded_video' => 'الأصل المحدّد ليس فيديو مرفوعاً صالحاً.',
    ],
    'created' => 'تم إنشاء الفيديو بنجاح.',
    'updated' => 'تم تحديث الفيديو بنجاح.',
    'deleted' => 'تم حذف الفيديو (قابل للاسترجاع).',
    'restored' => 'تم استرجاع الفيديو بنجاح.',
    'force_deleted' => 'تم حذف الفيديو نهائياً.',
    'not_deleted' => 'الفيديو غير محذوف.',
    'status_changed' => 'تم تغيير حالة الفيديو بنجاح.',
    'media_not_ready' => 'لا يمكن النشر/الجدولة: وسائط الفيديو غير جاهزة للتشغيل بعد.',
    'forbidden_transition' => 'لا تملك صلاحية هذا الانتقال.',
    'schedule_requires_date' => 'الجدولة تتطلّب تحديد تاريخ/وقت النشر.',
    'bulk_forbidden' => 'لا تملك صلاحية تنفيذ هذه العملية الجماعية.',
    'bulk_done' => 'تمّت معالجة :processed من :requested.',
    'not_found' => 'الفيديو غير موجود.',
    'invalid_locale' => 'لغة غير مدعومة.',
    'reprocess_queued' => 'تمت جدولة إعادة معالجة الوسائط.',
    'reprocess_unavailable' => 'إعادة المعالجة متاحة فقط لوسائط الفيديو المرفوعة.',

    // تفويض الكاتب (VideoAuthorizationGuard)
    'cannot_create' => 'لا تملك صلاحية إنشاء هذا المحتوى.',
    'writer_author_forbidden' => 'لا يمكن للكاتب إنشاء محتوى باسم غيره؛ يُنسَب تلقائياً إليه.',
    'author_not_found' => 'الكاتب المحدّد غير موجود.',
    'writer_cannot_edit_others' => 'لا يمكنك تعديل محتوى لا تملكه.',

    // سير عمل الانتقالات (VideoWorkflowGuard)
    'invalid_transition' => 'انتقال حالة غير مسموح به.',
    'writer_transition_forbidden' => 'لا يمكن للكاتب إلا إرسال محتواه للمراجعة.',
];
