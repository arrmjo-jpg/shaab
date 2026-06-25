<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Public\WriterRequest\CreateWriterRequestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\WriterRequest\StoreWriterRequestRequest;
use Illuminate\Http\JsonResponse;

class WriterRequestController extends Controller
{
    public function store(StoreWriterRequestRequest $request): JsonResponse
    {
        return (new CreateWriterRequestAction)->handle(
            $request->user(),
            $request->validated('note')
        );
    }
}
