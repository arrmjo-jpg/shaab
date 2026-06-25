<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * إشعار للكاتب عند تغيّر حالة محتواه تحريرياً (نشر/رفض فقط — لا in_review).
 *
 * قناة database فقط (تخزين داخل التطبيق يُقرأ عبر API الكاتب). **متزامن عمداً**
 * (بلا ShouldQueue): كتابة صفّ واحد سريعة لا تعتمد على queue worker شغّال —
 * يُرسَل بعد commit وخارج أي transaction عبر WriterNotifier. الرسالة المعروضة
 * تُركَّب وقت القراءة في NotificationResource عبر lang (لا نص مضمّن مخزَّن).
 */
class ContentStatusChanged extends Notification
{
    public function __construct(
        public string $contentType,
        public int $contentId,
        public string $title,
        public string $slug,
        public string $status,
    ) {}

    /**
     * @return array<int,string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * حمولة database — بيانات صرفة فقط (بلا نص مُعرَّب)؛ النص يُركَّب عند القراءة.
     *
     * @return array<string,mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'content_type' => $this->contentType,
            'content_id' => $this->contentId,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
        ];
    }
}
