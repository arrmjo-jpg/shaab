<?php

declare(strict_types=1);

namespace App\Actions\Admin\System;

use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * يحذف مهاماً فاشلة (واحدة/مختارة/الكلّ) عبر مزوّد queue.failer الرسمي
 * (forget/flush) — لا وصول خام لجدول failed_jobs.
 */
class DeleteFailedJobsAction
{
    /** @param  array<int,string>|null  $ids */
    public function handle(?array $ids, bool $all): JsonResponse
    {
        $failer = app('queue.failer');

        if ($all) {
            $failer->flush();

            return ApiResponse::success(__('failed_job.deleted'));
        }

        $ids = array_values(array_filter((array) $ids));
        if ($ids === []) {
            return ApiResponse::error(__('failed_job.none_selected'), [], 422);
        }

        foreach ($ids as $id) {
            $failer->forget($id);
        }

        return ApiResponse::success(__('failed_job.deleted'));
    }
}
