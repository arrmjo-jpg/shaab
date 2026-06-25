<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * إشعار «مباراة تتابعها تبدأ قريبًا» (نظام «تابع» — الكتلة B). قناة database فقط (الجرس داخل التطبيق؛ لا بريد —
 * تفادي إزعاج). **ShouldQueue** غير حاجب. حمولة بدائيّة (لا موديل) لتسلسل طابور نظيف. الرسالة المعروضة تُركَّب في
 * NotificationResource عبر lang وقت القراءة (الكتلة D) من `kind` — كنمط إشعارات الكاتب (لا نصّ مخزَّن).
 */
class MatchReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $gameId,
        private readonly ?int $competitionId,
        private readonly ?string $home,
        private readonly ?string $away,
        private readonly ?string $startAt,
    ) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return Schema::hasTable('notifications') ? ['database'] : [];
    }

    /** @return array<string,mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'match_reminder',
            'game_id' => $this->gameId,
            'competition_id' => $this->competitionId,
            'home' => $this->home,
            'away' => $this->away,
            'start_at' => $this->startAt,
        ];
    }
}
