<?php

declare(strict_types=1);

namespace App\Actions\Admin\Contact;

use App\Http\Resources\Admin\Contact\ContactMessageResource;
use App\Models\ContactMessage;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تغيير حالة رسالة اتصال عبر Eloquent save ⇒ تدقيق تلقائيّ (status ضمن auditAttributes).
 * الحالة مُتحقَّق منها في الـRequest (in_review/closed).
 */
class UpdateContactMessageStatusAction
{
    public function handle(ContactMessage $message, string $status): JsonResponse
    {
        $message->status = $status; // EnumCast
        $message->save();

        return ApiResponse::success(
            message: __('contact.status_changed'),
            data: new ContactMessageResource($message->loadMissing('repliedBy')),
        );
    }
}
