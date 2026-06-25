<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Http\Resources\Admin\Whatsapp\WhatsappGroupResource;
use App\Models\WhatsappGroup;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/** إنشاء مجموعة — الافتراضية الوحيدة تأتي من الـ Seeder؛ الجديدة دائماً غير افتراضية. */
class CreateWhatsappGroupAction
{
    /** @param  array<string,mixed>  $validated */
    public function handle(array $validated): JsonResponse
    {
        $group = WhatsappGroup::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_default' => false,
        ]);

        return ApiResponse::success(
            __('whatsapp.group.created'),
            new WhatsappGroupResource($group),
            201,
        );
    }
}
