<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Roles;

use App\Actions\Admin\Roles\CreateRoleAction;
use App\Actions\Admin\Roles\DeleteRoleAction;
use App\Actions\Admin\Roles\ListRolesAction;
use App\Actions\Admin\Roles\UpdateRoleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Roles\StoreRoleRequest;
use App\Http\Requests\Admin\Roles\UpdateRoleRequest;
use App\Http\Resources\Admin\Roles\RoleResource;
use App\Models\Role;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListRolesAction)->handle();
    }

    public function show(Role $role): JsonResponse
    {
        return ApiResponse::success(data: new RoleResource(
            $role->load('permissions')->loadCount(['permissions', 'users'])
        ));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        return (new CreateRoleAction)->handle($request->validated(), $request->user());
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        return (new UpdateRoleAction)->handle($role, $request->user(), $request->validated());
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        return (new DeleteRoleAction)->handle($role, $request->user());
    }
}
