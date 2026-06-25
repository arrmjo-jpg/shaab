<?php

declare(strict_types=1);

namespace App\Actions\Admin\Team;

use App\Http\Resources\Admin\Team\TeamMemberResource;
use App\Models\TeamMember;
use App\Support\Cache\TeamMemberCacheTags;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class RestoreTeamMemberAction
{
    public function handle(TeamMember $member): JsonResponse
    {
        if (! $member->trashed()) {
            return ApiResponse::error(__('team.not_trashed'), [], 422);
        }

        $member->restore();

        Cache::tags(TeamMemberCacheTags::invalidationTags($member))->flush();

        return ApiResponse::success(
            __('team.restored'),
            new TeamMemberResource($member->fresh()->load('avatarAsset'))
        );
    }
}
