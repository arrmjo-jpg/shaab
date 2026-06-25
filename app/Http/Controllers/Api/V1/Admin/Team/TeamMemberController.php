<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Team;

use App\Actions\Admin\Team\CreateTeamMemberAction;
use App\Actions\Admin\Team\DeleteTeamMemberAction;
use App\Actions\Admin\Team\ForceDeleteTeamMemberAction;
use App\Actions\Admin\Team\ListTeamMembersAction;
use App\Actions\Admin\Team\ReorderTeamMembersAction;
use App\Actions\Admin\Team\RestoreTeamMemberAction;
use App\Actions\Admin\Team\ToggleTeamMemberStatusAction;
use App\Actions\Admin\Team\UpdateTeamMemberAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Team\ReorderTeamMembersRequest;
use App\Http\Requests\Admin\Team\StoreTeamMemberRequest;
use App\Http\Requests\Admin\Team\ToggleTeamMemberStatusRequest;
use App\Http\Requests\Admin\Team\UpdateTeamMemberRequest;
use App\Http\Resources\Admin\Team\TeamMemberResource;
use App\Models\TeamMember;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تحكّم رفيع — كل المنطق في طبقة الـ Actions (Laravel-native، لا service layer).
 */
class TeamMemberController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListTeamMembersAction)->handle();
    }

    public function show(TeamMember $teamMember): JsonResponse
    {
        return ApiResponse::success(
            data: new TeamMemberResource($teamMember->load('avatarAsset'))
        );
    }

    public function store(StoreTeamMemberRequest $request): JsonResponse
    {
        return (new CreateTeamMemberAction)->handle($request->validated());
    }

    public function update(UpdateTeamMemberRequest $request, TeamMember $teamMember): JsonResponse
    {
        return (new UpdateTeamMemberAction)->handle($teamMember, $request->validated());
    }

    public function status(ToggleTeamMemberStatusRequest $request, TeamMember $teamMember): JsonResponse
    {
        return (new ToggleTeamMemberStatusAction)->handle($teamMember, $request->validated());
    }

    public function reorder(ReorderTeamMembersRequest $request): JsonResponse
    {
        return (new ReorderTeamMembersAction)->handle($request->validated()['ids']);
    }

    public function destroy(TeamMember $teamMember): JsonResponse
    {
        return (new DeleteTeamMemberAction)->handle($teamMember);
    }

    public function restore(TeamMember $teamMember): JsonResponse
    {
        return (new RestoreTeamMemberAction)->handle($teamMember);
    }

    public function forceDelete(TeamMember $teamMember): JsonResponse
    {
        return (new ForceDeleteTeamMemberAction)->handle($teamMember);
    }
}
