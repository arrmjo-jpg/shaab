<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\System;

use App\Actions\Admin\System\OpsOverviewAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OpsController extends Controller
{
    public function overview(): JsonResponse
    {
        return (new OpsOverviewAction)->handle();
    }
}
