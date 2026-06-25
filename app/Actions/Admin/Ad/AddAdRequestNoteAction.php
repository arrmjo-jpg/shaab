<?php

declare(strict_types=1);

namespace App\Actions\Admin\Ad;

use App\Http\Resources\Admin\Ad\AdRequestResource;
use App\Models\AdRequest;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إضافة ملاحظة داخليّة كسجلّ جديد (لا overwrite) — يحفظ التاريخ كاملاً مع الكاتب. يعيد الطلب
 * مع ملاحظاته المُحمّلة.
 */
class AddAdRequestNoteAction
{
    public function handle(AdRequest $adRequest, string $body, int $userId): JsonResponse
    {
        $adRequest->notes()->create([
            'user_id' => $userId,
            'body' => $body,
        ]);

        return ApiResponse::success(
            message: __('ad_request.note_added'),
            data: new AdRequestResource($adRequest->load(['reviewedBy', 'notes.user'])),
        );
    }
}
