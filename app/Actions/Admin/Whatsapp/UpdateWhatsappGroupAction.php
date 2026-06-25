<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Http\Resources\Admin\Whatsapp\WhatsappGroupResource;
use App\Models\WhatsappGroup;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class UpdateWhatsappGroupAction
{
    /** @param  array<string,mixed>  $validated */
    public function handle(WhatsappGroup $group, array $validated): JsonResponse
    {
        foreach (['name', 'description'] as $field) {
            if (array_key_exists($field, $validated)) {
                $group->{$field} = $validated[$field];
            }
        }

        $group->save();

        return ApiResponse::success(
            __('whatsapp.group.updated'),
            new WhatsappGroupResource($group->loadCount('contacts')),
        );
    }
}
