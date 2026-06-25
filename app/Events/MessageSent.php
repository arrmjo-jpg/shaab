<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\Admin\Chat\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * بثّ رسالة شات جديدة. after-commit عبر خاصية $afterCommit (Laravel 13 لا تملك
 * واجهة ShouldBroadcastAfterCommit): لا يصل الحدث للواجهة قبل نجاح معاملة الكتابة
 * (REST هو مصدر الحقيقة؛ Reverb طبقة نقل فقط).
 *
 * يُبَثّ عبر toOthers() في الـ Action، فالمرسِل لا يستقبله (حصل عليه من ردّ REST) —
 * يزيل ازدواج REST/Echo من المصدر. الحمولة نفس شكل MessageResource (id موحّد للدمج
 * والترتيب)، مع mine=false (كل المستلمين «آخرون»).
 */
class MessageSent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** لا يُبَثّ إلا بعد نجاح معاملة الكتابة. */
    public bool $afterCommit = true;

    public function __construct(public Message $message) {}

    /**
     * @return array<int,PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.conversation.'.$this->message->conversation_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string,mixed>
     */
    public function broadcastWith(): array
    {
        $data = (new MessageResource(
            $this->message->load('sender:id,name,avatar', 'attachment')
        ))->resolve();

        // toOthers يضمن أن المستلمين ليسوا المرسِل — mine=false دائماً لهم.
        $data['mine'] = false;

        return $data;
    }
}
