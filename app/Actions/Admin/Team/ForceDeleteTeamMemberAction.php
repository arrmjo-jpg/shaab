<?php

declare(strict_types=1);

namespace App\Actions\Admin\Team;

use App\Models\TeamMember;
use App\Support\Cache\TeamMemberCacheTags;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ForceDeleteTeamMemberAction
{
    public function handle(TeamMember $member): JsonResponse
    {
        // التقاط الوسوم قبل الحذف (نحتاج slug للإبطال).
        $tags = TeamMemberCacheTags::invalidationTags($member);

        // url_history مرتبط بـ cascadeOnDelete — يُحذف تلقائياً مع العضو.
        $member->forceDelete();

        Cache::tags($tags)->flush();

        return ApiResponse::success(__('team.force_deleted'));
    }
}
