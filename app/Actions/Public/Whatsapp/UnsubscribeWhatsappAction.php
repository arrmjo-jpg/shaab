<?php

declare(strict_types=1);

namespace App\Actions\Public\Whatsapp;

use App\Enums\WhatsappContactStatus;
use App\Models\WhatsappContact;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إلغاء اشتراك عامّ بالتوكن — idempotent (نجاح موحّد سواء وُجد التوكن أو لا، تفادياً للتعداد).
 * لا يُحذف المشترك؛ فقط status=unsubscribed (يُستبعَد من المستلمين).
 */
class UnsubscribeWhatsappAction
{
    /** @param  array<string,mixed>  $data */
    public function handle(array $data): JsonResponse
    {
        $contact = WhatsappContact::query()->where('unsubscribe_token', $data['token'])->first();

        if ($contact !== null && $contact->status !== WhatsappContactStatus::Unsubscribed) {
            $contact->status = WhatsappContactStatus::Unsubscribed;
            $contact->save();
        }

        return ApiResponse::success(message: __('whatsapp.public.unsubscribed'));
    }
}
