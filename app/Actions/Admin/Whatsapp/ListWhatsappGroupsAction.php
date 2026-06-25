<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Http\Resources\Admin\Whatsapp\WhatsappGroupResource;
use App\Models\WhatsappGroup;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/** قائمة مجموعات واتساب (مسطّحة — أعدادها صغيرة بطبيعتها) مع عدّاد الأعضاء. */
class ListWhatsappGroupsAction
{
    public function handle(): JsonResponse
    {
        $groups = WhatsappGroup::query()
            ->withCount('contacts')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(data: WhatsappGroupResource::collection($groups)->resolve());
    }
}
