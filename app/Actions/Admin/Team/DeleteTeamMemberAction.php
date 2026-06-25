<?php

declare(strict_types=1);

namespace App\Actions\Admin\Team;

use App\Models\TeamMember;
use App\Support\Cache\TeamMemberCacheTags;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DeleteTeamMemberAction
{
    public function handle(TeamMember $member): JsonResponse
    {
        $member->delete(); // soft delete

        Cache::tags(TeamMemberCacheTags::invalidationTags($member))->flush();

        return ApiResponse::success(__('team.deleted'));
    }
}
