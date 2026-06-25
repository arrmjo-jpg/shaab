<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Inbox;

use App\Actions\Admin\Inbox\InboxUnreadCountAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * صندوق الإدارة الموحّد (اتصل بنا + طلبات الإعلان) — عدّاد Badge فقط حاليّاً.
 */
class InboxController extends Controller
{
    /** عدّاد غير المقروء (status='new') للوحدتين + الإجمالي — مصدر Badge. */
    public function unreadCount(): JsonResponse
    {
        return (new InboxUnreadCountAction)->handle();
    }
}
