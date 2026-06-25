<?php

declare(strict_types=1);

namespace App\Actions\Public\Contact;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Support\Notifications\AdminContactNotifier;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إنشاء رسالة «اتصل بنا» من زائر عامّ. status=new (يقود Badge). meta يلتقط ip/ua للحماية.
 * إشعار الإدارة (post-commit Notifier) يُوصَل في مرحلة Notifications.
 */
class CreateContactMessageAction
{
    /** @param  array<string,mixed>  $data */
    public function handle(array $data, Request $request): JsonResponse
    {
        $message = ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'subject' => $data['subject'],
            'type' => $data['type'],
            'message' => $data['message'],
            'status' => ContactMessageStatus::New->value,
            'meta' => [
                'ip' => $request->ip(),
                'ua' => mb_substr((string) $request->userAgent(), 0, 500),
            ],
        ]);

        // إشعار الإدارة post-commit (best-effort، يُعيد استخدام نظام الإشعارات الحاليّ).
        AdminContactNotifier::created($message);

        return ApiResponse::success(message: __('contact.created'), status: 201);
    }
}
