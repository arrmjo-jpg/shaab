<?php

declare(strict_types=1);

namespace App\Actions\Admin\WriterRequests;

use App\Enums\WriterRequestStatus;
use App\Http\Resources\Admin\WriterRequests\WriterRequestResource;
use App\Models\User;
use App\Models\WriterRequest;
use App\Support\Notifications\WriterRequestNotifier;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ApproveWriterRequestAction
{
    public function handle(WriterRequest $writerRequest, User $actor): JsonResponse
    {
        // فحص أوّلي رخيص (fast-fail قبل القفل) — نفس سلوك 422 السابق للحالة الشائعة.
        if ($writerRequest->status !== WriterRequestStatus::Pending) {
            return ApiResponse::error(__('writer_request.not_pending'), [], 422);
        }

        // ذرّية + إغلاق سباق approve/reject: إعادة جلب الصفّ مقفولاً (lockForUpdate) وإعادة
        // فحص الحالة تحت القفل قبل القبول + الترقية معاً. يُرجَع الصفّ المحدَّث أو null (خسر السباق).
        $approved = DB::transaction(function () use ($writerRequest, $actor): ?WriterRequest {
            $locked = WriterRequest::whereKey($writerRequest->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->status !== WriterRequestStatus::Pending) {
                return null;
            }

            $locked->update([
                'status' => WriterRequestStatus::Approved->value,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            $locked->user->forceFill(['is_writer' => true])->save();

            return $locked;
        });

        if ($approved === null) {
            return ApiResponse::error(__('writer_request.not_pending'), [], 422);
        }

        // إشعار المُقدِّم (database + mail، ShouldQueue) — بعد commit وخارج الـtransaction (best-effort).
        WriterRequestNotifier::approved($approved);

        return ApiResponse::success(
            __('writer_request.approved'),
            new WriterRequestResource($approved->load(['user:id,name,email', 'reviewer:id,name']))
        );
    }
}
