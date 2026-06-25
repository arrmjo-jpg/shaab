<?php

declare(strict_types=1);

namespace App\Actions\Admin\Chat;

use App\Events\MessageSent;
use App\Http\Resources\Admin\Chat\MessageResource;
use App\Models\Conversation;
use App\Models\MediaAsset;
use App\Models\Message;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * إرسال رسالة. بوابة العضوية أولاً. body نصّ صِرف (trim — لا HTML). يحدّث
 * last_message_at للفرز. (البثّ الحيّ عبر Reverb يُضاف في سلايس الـ realtime.)
 */
class SendMessageAction
{
    public function handle(User $actor, Conversation $conversation, array $validated): JsonResponse
    {
        if (! ChatAccess::isParticipant($conversation, $actor)) {
            return ApiResponse::error(__('chat.forbidden'), [], 403);
        }

        // حارس المرفق: صورة رفعها المرسِل نفسه (composer الشات يرفع طازجاً). يمنع
        // تمرير ID عشوائي لأصل لا يملكه المستخدم (لا يكفي exists على مستوى الـ Request).
        $attachmentId = $validated['attachment_asset_id'] ?? null;
        if ($attachmentId !== null && ! $this->ownsImageAsset($actor, (int) $attachmentId)) {
            return ApiResponse::error(__('chat.invalid_attachment'), [], 422);
        }

        $message = DB::transaction(function () use ($actor, $conversation, $validated): Message {
            $message = $conversation->messages()->create([
                'user_id' => $actor->id,
                'body' => trim((string) ($validated['body'] ?? '')),
                'attachment_asset_id' => $validated['attachment_asset_id'] ?? null,
            ]);

            $conversation->forceFill(['last_message_at' => $message->created_at])->save();

            return $message;
        });

        // بثّ حيّ للأعضاء الآخرين فقط (toOthers): المرسِل حصل على الرسالة من ردّ REST،
        // فلا ازدواج. الحدث AfterCommit — لا يصل قبل نجاح المعاملة أعلاه.
        broadcast(new MessageSent($message))->toOthers();

        return ApiResponse::success(
            __('chat.sent'),
            new MessageResource($message->load('sender:id,name,avatar', 'attachment')),
            201,
        );
    }

    /** الأصل موجود، صورة، ورفعه الفاعل نفسه (ملكية فعلية لا مجرّد وجود). */
    private function ownsImageAsset(User $actor, int $assetId): bool
    {
        return MediaAsset::query()
            ->whereKey($assetId)
            ->where('uploaded_by', $actor->id)
            ->where('mime_type', 'like', 'image/%')
            ->exists();
    }
}
