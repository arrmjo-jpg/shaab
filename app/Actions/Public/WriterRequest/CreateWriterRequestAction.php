<?php

declare(strict_types=1);

namespace App\Actions\Public\WriterRequest;

use App\Enums\WriterRequestStatus;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class CreateWriterRequestAction
{
    public function handle(User $user, ?string $note): JsonResponse
    {
        if ($user->is_writer) {
            return ApiResponse::error(__('writer_request.already_writer'), [], 422);
        }

        // سياسة إعادة التقديم (مقصودة): يُمنع فقط وجود طلب "معلّق".
        // المرفوض سابقاً يحقّ له التقديم من جديد (لا حظر دائم).
        $hasPending = $user->writerRequests()
            ->where('status', WriterRequestStatus::Pending->value)
            ->exists();

        if ($hasPending) {
            return ApiResponse::error(__('writer_request.already_pending'), [], 422);
        }

        $user->writerRequests()->create([
            'status' => WriterRequestStatus::Pending->value,
            'note' => $note,
        ]);

        return ApiResponse::success(__('writer_request.submitted'), null, 201);
    }
}
