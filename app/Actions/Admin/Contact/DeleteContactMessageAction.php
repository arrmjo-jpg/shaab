<?php

declare(strict_types=1);

namespace App\Actions\Admin\Contact;

use App\Models\ContactMessage;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حذف ناعم لرسالة اتصال (SoftDeletes) — لا حذف نهائيّ تلقائيّ. التدقيق يلتقط الحذف.
 */
class DeleteContactMessageAction
{
    public function handle(ContactMessage $message): JsonResponse
    {
        $message->delete();

        return ApiResponse::success(message: __('contact.deleted'));
    }
}
