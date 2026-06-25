<?php

declare(strict_types=1);

namespace App\Actions\Admin\Contact;

use App\Http\Resources\Admin\Contact\ContactMessageResource;
use App\Models\ContactMessage;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Mark as Read — يضبط read_at مرّة واحدة (أوّل مشاهدة). seen metadata بصريّة فقط؛
 * لا يغيّر status ولا يقود Badge (الـBadge من count(status='new')). read_at ليس مُدقَّقاً.
 */
class MarkContactMessageReadAction
{
    public function handle(ContactMessage $message): JsonResponse
    {
        if ($message->read_at === null) {
            $message->read_at = now();
            $message->save();
        }

        return ApiResponse::success(
            data: new ContactMessageResource($message->loadMissing('repliedBy')),
        );
    }
}
