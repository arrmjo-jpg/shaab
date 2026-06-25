<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Support\Responses\ApiResponse;
use App\Support\Whatsapp\WhatsappRecipients;
use Illuminate\Http\JsonResponse;

/** عدد المستلمين المتوقَّع لمجموعات مختارة (مشترك فريد) — يُعرَض قبل الإرسال. */
class CountWhatsappRecipientsAction
{
    /** @param  array<string,mixed>  $validated */
    public function handle(array $validated): JsonResponse
    {
        $groupIds = array_map('intval', $validated['groups']);

        return ApiResponse::success(data: ['recipients' => WhatsappRecipients::count($groupIds)]);
    }
}
