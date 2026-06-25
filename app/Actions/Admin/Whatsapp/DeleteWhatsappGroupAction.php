<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Models\WhatsappGroup;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/** حذف مجموعة (ناعم) — المجموعة الافتراضية «مشتركو الموقع» محمية من الحذف. */
class DeleteWhatsappGroupAction
{
    public function handle(WhatsappGroup $group): JsonResponse
    {
        if ($group->is_default) {
            return ApiResponse::error(__('whatsapp.group.default_locked'), [], 422);
        }

        $group->delete();

        return ApiResponse::success(__('whatsapp.group.deleted'));
    }
}
