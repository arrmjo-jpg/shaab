<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Permissions;

use App\Actions\Admin\Permissions\ListPermissionGroupsAction;
use App\Actions\Admin\Permissions\ListPermissionsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListPermissionsAction)->handle();
    }

    public function groups(): JsonResponse
    {
        return (new ListPermissionGroupsAction)->handle();
    }
}
