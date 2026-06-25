<?php

declare(strict_types=1);

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * تمثيل إشعار للكاتب (DatabaseNotification). يُركّب الرسالة المعروضة عبر lang
 * وقت القراءة (لا نص مخزَّن). data يحوي: content_type, content_id, title, slug, status.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->data) ? $this->data : [];

        // إشعارات «تابع» (المرحلة 2) — تذكير ما قبل المباراة + الأحداث المباشرة (هدف/بطاقة). عنوان+رسالة+رابط
        // موحَّد لكلّ نوع؛ الرابط لا يكون null أبداً (المباراة ← البطولة ← /sport).
        $kind = (string) ($data['kind'] ?? '');
        if ($kind === 'match_reminder' || $kind === 'match_event') {
            return $this->followNotification($data, $kind);
        }

        // إشعارات طلب الكاتب (P1.4) — حمولة {kind:'writer_request', event}؛ بلا حقول محتوى.
        if ($kind === 'writer_request') {
            $event = (string) ($data['event'] ?? '');

            return [
                'id' => $this->id,
                'content_type' => null,
                'content_id' => null,
                'title' => null,
                'slug' => null,
                'status' => $event !== '' ? $event : null,
                'message' => $event !== '' ? __('notification.writer_request.'.$event) : null,
                'read' => $this->read_at !== null,
                'read_at' => $this->read_at?->toIso8601String(),
                'created_at' => $this->created_at?->toIso8601String(),
            ];
        }

        $type = (string) ($data['content_type'] ?? '');
        $status = (string) ($data['status'] ?? '');
        $title = (string) ($data['title'] ?? '');

        return [
            'id' => $this->id,
            'content_type' => $type !== '' ? $type : null,
            'content_id' => $data['content_id'] ?? null,
            'title' => $title !== '' ? $title : null,
            'slug' => $data['slug'] ?? null,
            'status' => $status !== '' ? $status : null,
            'message' => $this->composeMessage($type, $status, $title),
            'read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** الرسالة المُعرَّبة (نشر/رفض فقط)؛ null لأي حالة أخرى أو حمولة ناقصة. */
    private function composeMessage(string $type, string $status, string $title): ?string
    {
        if (! in_array($status, ['published', 'rejected'], true) || $type === '') {
            return null;
        }

        return __('notification.content.'.$status, [
            'type' => __('notification.type.'.$type),
            'title' => $title,
        ]);
    }

    /**
     * تمثيل إشعار «تابع» — عنوان + رسالة مُعرَّبة (من kind/نوع الحدث) + رابط لصفحة المباراة، مع احتياطيّ
     * (البطولة ← /sport) فلا يكون الرابط null أبداً.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function followNotification(array $data, string $kind): array
    {
        $gameId = isset($data['game_id']) ? (int) $data['game_id'] : 0;
        $competitionId = isset($data['competition_id']) ? (int) $data['competition_id'] : 0;

        // الرابط: المباراة أوّلاً، ثمّ البطولة، ثمّ /sport — لا null أبداً.
        $url = $gameId > 0
            ? "/sport/match/{$gameId}"
            : ($competitionId > 0 ? "/sport/competition/{$competitionId}" : '/sport');

        $key = $kind === 'match_reminder'
            ? 'match_reminder'
            : $this->eventKey(isset($data['event_type_id']) ? (int) $data['event_type_id'] : 0);

        $placeholders = [
            'home' => (string) ($data['home'] ?? ''),
            'away' => (string) ($data['away'] ?? ''),
            'player' => (string) ($data['player'] ?? ''),
            'minute' => (string) ($data['minute'] ?? ''),
            'label' => (string) ($data['label'] ?? ''),
        ];

        return [
            'id' => $this->id,
            'content_type' => null,
            'content_id' => $gameId > 0 ? $gameId : null,
            'title' => __('follow.notification.'.$key.'_title'),
            'slug' => null,
            'status' => null,
            'message' => __('follow.notification.'.$key, $placeholders),
            'url' => $url,
            'read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** نوع الحدث (eventType.id من 365) → مفتاح ترجمة. */
    private function eventKey(int $eventTypeId): string
    {
        return match ($eventTypeId) {
            1 => 'match_goal',
            2 => 'match_yellow_card',
            3 => 'match_red_card',
            default => 'match_event',
        };
    }
}
