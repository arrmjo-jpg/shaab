<?php

declare(strict_types=1);

namespace App\Actions\Admin\System;

use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * يعيد جدولة مهام فاشلة (واحدة/مختارة/الكلّ) عبر أمر queue:retry الرسمي — يدفعها
 * للطابور ويزيلها من failed_jobs. لا منطق طابور مخصّص (يُعاد استخدام آلية Laravel).
 *
 * fail-safe: حمولة تالفة/غير قابلة لفكّ التشفير قد تُفشِل queue:retry لمهمة بعينها
 * — نلتقط الاستثناء ونسجّله بدل إرجاع 500 (best-effort؛ تُحدِّث القائمة ما تبقّى).
 */
class RetryFailedJobsAction
{
    /** @param  array<int,string>|null  $ids */
    public function handle(?array $ids, bool $all): JsonResponse
    {
        if (! $all) {
            $ids = array_values(array_filter((array) $ids));
            if ($ids === []) {
                return ApiResponse::error(__('failed_job.none_selected'), [], 422);
            }
        }

        try {
            Artisan::call('queue:retry', ['id' => $all ? ['all'] : $ids]);
        } catch (Throwable $e) {
            Log::warning('RetryFailedJobsAction: queue:retry partial failure', [
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);
        }

        return ApiResponse::success(__('failed_job.retried'));
    }
}
