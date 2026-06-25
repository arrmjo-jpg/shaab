<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Permissions;

use App\Actions\Admin\Permissions\CreatePermissionGroupAction;
use App\Actions\Admin\Permissions\DeletePermissionGroupAction;
use App\Actions\Admin\Permissions\UpdatePermissionGroupAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Permissions\StorePermissionGroupRequest;
use App\Http\Requests\Admin\Permissions\UpdatePermissionGroupRequest;
use App\Models\PermissionGroup;
use Illuminate\Http\JsonResponse;

class PermissionGroupController extends Controller
{
    public function store(StorePermissionGroupRequest $request): JsonResponse
    {
        return (new CreatePermissionGroupAction)->handle($request->validated());
    }

    public function update(UpdatePermissionGroupRequest $request, PermissionGroup $permissionGroup): JsonResponse
    {
        return (new UpdatePermissionGroupAction)->handle($permissionGroup, $request->validated());
    }

    public function destroy(PermissionGroup $permissionGroup): JsonResponse
    {
        return (new DeletePermissionGroupAction)->handle($permissionGroup);
    }
}
