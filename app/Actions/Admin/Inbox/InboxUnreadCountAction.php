<?php

declare(strict_types=1);

namespace App\Actions\Admin\Inbox;

use App\Enums\AdRequestStatus;
use App\Enums\ContactMessageStatus;
use App\Models\AdRequest;
use App\Models\ContactMessage;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * عدّاد Badge الموحّد للوحدتين — **المصدر الوحيد للحقيقة**: count(status='new') من جداول
 * المصدر مباشرةً (لا من notifications). يَعُدّ فقط ما يملك المستخدم صلاحية رؤيته (لا تسريب).
 */
class InboxUnreadCountAction
{
    public function handle(): JsonResponse
    {
        $user = request()->user();

        $contact = ($user && $user->can('contact-messages.view'))
            ? ContactMessage::where('status', ContactMessageStatus::New->value)->count()
            : 0;

        $ad = ($user && $user->can('ad-requests.view'))
            ? AdRequest::where('status', AdRequestStatus::New->value)->count()
            : 0;

        return ApiResponse::success(data: [
            'contact_count' => $contact,
            'ad_count' => $ad,
            'total' => $contact + $ad,
        ]);
    }
}
