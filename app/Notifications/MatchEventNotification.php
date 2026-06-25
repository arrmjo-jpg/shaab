<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * إشعار حدثٍ مباشر (هدف/بطاقة) في مباراةٍ يتابعها المستخدم (نظام «تابع» — الكتلة C). قناة database فقط، ShouldQueue.
 * حمولة بدائيّة؛ الرسالة تُركَّب في NotificationResource (الكتلة D) من `kind=match_event` + الحقول.
 */
class MatchEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $gameId,
        private readonly ?int $competitionId,
        private readonly int $eventTypeId,
        private readonly string $label,
        private readonly string $minute,
        private readonly ?string $playerName,
        private readonly ?string $homeName,
        private readonly ?string $awayName,
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
            'kind' => 'match_event',
            'game_id' => $this->gameId,
            'competition_id' => $this->competitionId,
            'event_type_id' => $this->eventTypeId,
            'label' => $this->label,
            'minute' => $this->minute,
            'player' => $this->playerName,
            'home' => $this->homeName,
            'away' => $this->awayName,
        ];
    }
}
