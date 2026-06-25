<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Enums\WhatsappContactStatus;
use App\Http\Resources\Admin\Whatsapp\WhatsappContactResource;
use App\Models\WhatsappContact;
use App\Support\Responses\ApiResponse;
use App\Support\Whatsapp\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * إنشاء جهة اتصال — تطبيع الرقم إلى E.164 (رفض المحلي/غير الصالح) + منع التكرار.
 * رقم محذوف ناعماً سابقاً ⇒ يُستعاد بالبيانات الجديدة (unique على العمود يمنع صفاً ثانياً).
 */
class CreateWhatsappContactAction
{
    /** @param  array<string,mixed>  $validated */
    public function handle(array $validated): JsonResponse
    {
        $phone = PhoneNumber::normalize((string) $validated['phone']);
        if ($phone === null) {
            return ApiResponse::error(__('whatsapp.contact.invalid_phone'), [], 422);
        }

        $existing = WhatsappContact::withTrashed()->where('phone', $phone)->first();

        if ($existing !== null && ! $existing->trashed()) {
            return ApiResponse::error(__('whatsapp.contact.duplicate_phone'), [], 422);
        }

        /** @var array<int,int> $groupIds */
        $groupIds = array_map('intval', $validated['groups']);

        $contact = DB::transaction(function () use ($existing, $validated, $phone, $groupIds): WhatsappContact {
            if ($existing !== null) {
                // محذوف ناعماً بنفس الرقم — استعادة بالبيانات الجديدة (لا صف مكرر).
                $existing->restore();
                $existing->name = (string) $validated['name'];
                $existing->status = WhatsappContactStatus::Subscribed;
                $existing->source = 'manual';
                $existing->save();
                $existing->groups()->sync($groupIds);

                return $existing;
            }

            $contact = WhatsappContact::create([
                'name' => (string) $validated['name'],
                'phone' => $phone,
                'status' => WhatsappContactStatus::Subscribed->value,
                'source' => 'manual',
            ]);
            $contact->groups()->sync($groupIds);

            return $contact;
        });

        return ApiResponse::success(
            __('whatsapp.contact.created'),
            new WhatsappContactResource($contact->load('groups:id,name')),
            201,
        );
    }
}
