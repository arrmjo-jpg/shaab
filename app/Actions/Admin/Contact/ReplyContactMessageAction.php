<?php

declare(strict_types=1);

namespace App\Actions\Admin\Contact;

use App\Enums\ContactMessageStatus;
use App\Http\Resources\Admin\Contact\ContactMessageResource;
use App\Models\ContactMessage;
use App\Notifications\ContactReplyNotification;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * ردّ الإدارة على رسالة اتصال (القرار المعتمد):
 *   1) يُحفظ نصّ الردّ + وقته + المستخدم أوّلاً (لا فقدان سجلّ حتى لو فشل البريد).
 *   2) يُرسَل البريد للمُرسِل **متزامناً** (لمعرفة النجاح).
 *   3) status='replied' فقط **بعد نجاح الإرسال**؛ الفشل ⇒ تبقى الحالة + خطأ للإدارة + Log.
 */
class ReplyContactMessageAction
{
    public function handle(ContactMessage $message, string $body, int $userId): JsonResponse
    {
        // 1) حفظ الردّ أوّلاً — الحالة لا تتغيّر بعد.
        $message->reply_body = $body;
        $message->replied_at = now();
        $message->replied_by = $userId;
        $message->save();

        // 2) إرسال متزامن (on-demand) لبريد المُرسِل.
        try {
            Notification::route('mail', $message->email)
                ->notify(new ContactReplyNotification($message->subject, $body));
        } catch (\Throwable $e) {
            Log::warning('contact reply mail failed', [
                'contact_message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            // الفشل ⇒ لا تتحوّل replied؛ نُبلّغ الإدارة (المُرسِل الحاليّ يرى الخطأ).
            return ApiResponse::error(__('contact.reply_mail_failed'), [], 502);
        }

        // 3) نجاح ⇒ status=replied (تدقيق تلقائيّ).
        $message->status = ContactMessageStatus::Replied->value;
        $message->save();

        return ApiResponse::success(
            message: __('contact.replied'),
            data: new ContactMessageResource($message->loadMissing('repliedBy')),
        );
    }
}
