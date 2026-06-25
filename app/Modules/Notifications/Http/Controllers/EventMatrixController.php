<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Http\Requests\UpdateEventChannelRequest;
use App\Modules\Notifications\Http\Resources\EventChannelResource;
use App\Modules\Notifications\Http\Resources\EventMatrixResource;
use App\Modules\Notifications\Models\NotificationEventChannel;
use App\Modules\Notifications\Models\NotificationEventType;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * مصفوفة (event × channel) — الكتالوج SoT لوجود الأحداث (تُزامَن عبر notifications:sync-catalog).
 * هنا يُحرّر الأدمن **السلوك**: تفعيل الحدث + mode/priority/fallback/template/audience لكلّ قناة.
 * هذا الإعداد يُقرأ **عند إنشاء الحملة فقط** ويُجمَّد في snapshot القناة (لا قراءة حيّة وقت الإرسال).
 */
final class EventMatrixController extends Controller
{
    public function index(): JsonResponse
    {
        $events = NotificationEventType::query()
            ->where('archived', false)
            ->with(['channels' => fn ($q) => $q->orderBy('channel_priority')])
            ->orderBy('category')
            ->orderBy('key')
            ->get();

        return ApiResponse::success(data: EventMatrixResource::collection($events)->resolve());
    }

    public function updateChannel(UpdateEventChannelRequest $request, NotificationEventChannel $eventChannel): JsonResponse
    {
        $eventChannel->update($request->validated());

        return ApiResponse::success(
            message: 'تم تحديث إعداد القناة',
            data: (new EventChannelResource($eventChannel->refresh()))->resolve(),
        );
    }

    public function toggleEvent(NotificationEventType $event): JsonResponse
    {
        $event->update(['enabled' => ! $event->enabled]);

        return ApiResponse::success(
            message: $event->enabled ? 'تم تفعيل الحدث' : 'تم تعطيل الحدث',
            data: ['key' => $event->key, 'enabled' => (bool) $event->enabled],
        );
    }
}
