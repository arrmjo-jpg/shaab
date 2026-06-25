<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\System;

use App\Actions\Admin\System\DeleteFailedJobsAction;
use App\Actions\Admin\System\ListFailedJobsAction;
use App\Actions\Admin\System\RetryFailedJobsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\System\ManageFailedJobsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FailedJobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return (new ListFailedJobsAction)->handle($request->only(['q', 'page', 'per_page']));
    }

    public function retry(ManageFailedJobsRequest $request): JsonResponse
    {
        return (new RetryFailedJobsAction)->handle(
            $request->validated('ids'),
            (bool) $request->validated('all', false)
        );
    }

    public function destroy(ManageFailedJobsRequest $request): JsonResponse
    {
        return (new DeleteFailedJobsAction)->handle(
            $request->validated('ids'),
            (bool) $request->validated('all', false)
        );
    }
}
