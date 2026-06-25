<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Activity;

use App\Actions\Admin\Activity\ListActivityLogAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ActivityController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListActivityLogAction)->handle();
    }
}
