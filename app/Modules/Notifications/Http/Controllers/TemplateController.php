<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Http\Requests\TemplateRequest;
use App\Modules\Notifications\Http\Resources\TemplateResource;
use App\Modules\Notifications\Models\NotificationTemplate;
use App\Modules\Notifications\Support\EventCatalog;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * قوالب الإشعار (event × channel × locale). يُصيَّر القالب **مرّة واحدة عند إنشاء الحملة** ويُجمَّد
 * في snapshot القناة — لذا تعديل/حذف قالب **لا يؤثّر على الحملات القائمة** (immutable). المتغيّرات
 * مقيّدة بكتالوج الحدث. التفويض عبر permission middleware.
 */
final class TemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = NotificationTemplate::query()
            ->when(request()->filled('event_key'), fn ($q) => $q->where('event_key', request('event_key')))
            ->when(request()->filled('channel'), fn ($q) => $q->where('channel', request('channel')))
            ->orderBy('event_key')->orderBy('channel')->orderBy('locale')
            ->get();

        return ApiResponse::success(data: TemplateResource::collection($templates)->resolve());
    }

    public function show(NotificationTemplate $template): JsonResponse
    {
        return ApiResponse::success(data: (new TemplateResource($template))->resolve());
    }

    public function store(TemplateRequest $request): JsonResponse
    {
        $template = NotificationTemplate::query()->create($request->validated());

        return ApiResponse::success(
            message: 'تم إنشاء القالب',
            data: (new TemplateResource($template))->resolve(),
            status: 201,
        );
    }

    public function update(TemplateRequest $request, NotificationTemplate $template): JsonResponse
    {
        $template->update($request->validated());

        return ApiResponse::success(
            message: 'تم تحديث القالب (لا يؤثّر على الحملات القائمة)',
            data: (new TemplateResource($template->refresh()))->resolve(),
        );
    }

    public function destroy(NotificationTemplate $template): JsonResponse
    {
        $template->delete();

        return ApiResponse::success(message: 'تم حذف القالب');
    }

    /** المتغيّرات الموثّقة لحدثٍ (لمحرّر القوالب). */
    public function variables(): JsonResponse
    {
        $key = (string) request('event_key', '');

        return ApiResponse::success(data: [
            'event_key' => $key,
            'variables' => EventCatalog::variablesFor($key),
        ]);
    }
}
