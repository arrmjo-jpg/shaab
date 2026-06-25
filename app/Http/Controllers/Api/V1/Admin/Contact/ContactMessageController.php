<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Contact;

use App\Actions\Admin\Contact\DeleteContactMessageAction;
use App\Actions\Admin\Contact\ListContactMessagesAction;
use App\Actions\Admin\Contact\MarkContactMessageReadAction;
use App\Actions\Admin\Contact\ReplyContactMessageAction;
use App\Actions\Admin\Contact\UpdateContactMessageStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Contact\ReplyContactMessageRequest;
use App\Http\Requests\Admin\Contact\UpdateContactMessageStatusRequest;
use App\Http\Resources\Admin\Contact\ContactMessageResource;
use App\Models\ContactMessage;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ContactMessageController extends Controller
{
    /** قائمة رسائل الاتصال — contact-messages.view. */
    public function index(): JsonResponse
    {
        return (new ListContactMessagesAction)->handle();
    }

    /** تفاصيل رسالة — contact-messages.view. */
    public function show(ContactMessage $contactMessage): JsonResponse
    {
        return ApiResponse::success(
            data: new ContactMessageResource($contactMessage->loadMissing('repliedBy')),
        );
    }

    /** تغيير الحالة (in_review/closed) — contact-messages.reply. */
    public function updateStatus(UpdateContactMessageStatusRequest $request, ContactMessage $contactMessage): JsonResponse
    {
        return (new UpdateContactMessageStatusAction)->handle($contactMessage, $request->validated()['status']);
    }

    /** ردّ الإدارة (يحفظ الردّ ثمّ يرسل البريد؛ replied عند النجاح فقط) — contact-messages.reply. */
    public function reply(ReplyContactMessageRequest $request, ContactMessage $contactMessage): JsonResponse
    {
        return (new ReplyContactMessageAction)->handle(
            $contactMessage,
            $request->validated()['body'],
            (int) $request->user()->id,
        );
    }

    /** Mark as Read (seen) — contact-messages.view. */
    public function markRead(ContactMessage $contactMessage): JsonResponse
    {
        return (new MarkContactMessageReadAction)->handle($contactMessage);
    }

    /** حذف ناعم — contact-messages.delete. */
    public function destroy(ContactMessage $contactMessage): JsonResponse
    {
        return (new DeleteContactMessageAction)->handle($contactMessage);
    }
}
