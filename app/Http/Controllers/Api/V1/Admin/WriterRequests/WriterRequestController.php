<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\WriterRequests;

use App\Actions\Admin\WriterRequests\ApproveWriterRequestAction;
use App\Actions\Admin\WriterRequests\ListWriterRequestsAction;
use App\Actions\Admin\WriterRequests\RejectWriterRequestAction;
use App\Http\Controllers\Controller;
use App\Models\WriterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WriterRequestController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListWriterRequestsAction)->handle();
    }

    public function approve(Request $request, WriterRequest $writerRequest): JsonResponse
    {
        return (new ApproveWriterRequestAction)->handle($writerRequest, $request->user());
    }

    public function reject(Request $request, WriterRequest $writerRequest): JsonResponse
    {
        return (new RejectWriterRequestAction)->handle($writerRequest, $request->user());
    }
}
