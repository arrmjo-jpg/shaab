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

class RejectWriterRequestAction
{
    public function handle(WriterRequest $writerRequest, User $actor): JsonResponse
    {
        // فحص أوّلي رخيص (fast-fail قبل القفل) — نفس سلوك 422 السابق للحالة الشائعة.
        if ($writerRequest->status !== WriterRequestStatus::Pending) {
            return ApiResponse::error(__('writer_request.not_pending'), [], 422);
        }

        // ذرّية + إغلاق سباق approve/reject: إعادة جلب الصفّ مقفولاً وإعادة فحص الحالة تحت القفل
        // قبل الرفض. يُرجَع الصفّ المحدَّث أو null (خسر السباق).
        $rejected = DB::transaction(function () use ($writerRequest, $actor): ?WriterRequest {
            $locked = WriterRequest::whereKey($writerRequest->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->status !== WriterRequestStatus::Pending) {
                return null;
            }

            $locked->update([
                'status' => WriterRequestStatus::Rejected->value,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            return $locked;
        });

        if ($rejected === null) {
            return ApiResponse::error(__('writer_request.not_pending'), [], 422);
        }

        // إشعار المُقدِّم (mail فقط، ShouldQueue) — بعد commit وخارج الـtransaction (best-effort).
        WriterRequestNotifier::rejected($rejected);

        return ApiResponse::success(
            __('writer_request.rejected'),
            new WriterRequestResource($rejected->load(['user:id,name,email', 'reviewer:id,name']))
        );
    }
}
