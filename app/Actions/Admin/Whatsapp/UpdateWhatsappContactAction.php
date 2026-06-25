<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Http\Resources\Admin\Whatsapp\WhatsappContactResource;
use App\Models\WhatsappContact;
use App\Support\Responses\ApiResponse;
use App\Support\Whatsapp\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UpdateWhatsappContactAction
{
    /** @param  array<string,mixed>  $validated */
    public function handle(WhatsappContact $contact, array $validated): JsonResponse
    {
        if (array_key_exists('phone', $validated)) {
            $phone = PhoneNumber::normalize((string) $validated['phone']);
            if ($phone === null) {
                return ApiResponse::error(__('whatsapp.contact.invalid_phone'), [], 422);
            }

            $duplicate = WhatsappContact::withTrashed()
                ->where('phone', $phone)
                ->whereKeyNot($contact->id)
                ->exists();
            if ($duplicate) {
                return ApiResponse::error(__('whatsapp.contact.duplicate_phone'), [], 422);
            }

            $contact->phone = $phone;
        }

        if (array_key_exists('name', $validated)) {
            $contact->name = (string) $validated['name'];
        }

        DB::transaction(function () use ($contact, $validated): void {
            $contact->save();

            if (array_key_exists('groups', $validated)) {
                $contact->groups()->sync(array_map('intval', $validated['groups']));
            }
        });

        return ApiResponse::success(
            __('whatsapp.contact.updated'),
            new WhatsappContactResource($contact->load('groups:id,name')),
        );
    }
}
