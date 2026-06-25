<?php

declare(strict_types=1);

namespace App\Actions\Admin\Team;

use App\Enums\TeamMemberStatus;
use App\Http\Resources\Admin\Team\TeamMemberResource;
use App\Models\TeamMember;
use App\Support\Cache\TeamMemberCacheTags;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * تبديل حالة نشاط العضو (active/inactive). لا دورة حياة تحريرية — مجرّد إظهار/إخفاء
 * على الموقع. البوابة team.edit على المسار (لا صلاحية نشر/أرشفة منفصلة).
 */
class ToggleTeamMemberStatusAction
{
    public function handle(TeamMember $member, array $validated): JsonResponse
    {
        $member->status = TeamMemberStatus::from($validated['status'])->value;
        $member->save();

        Cache::tags(TeamMemberCacheTags::invalidationTags($member))->flush();

        return ApiResponse::success(
            __('team.status_changed'),
            new TeamMemberResource($member->fresh()->load('avatarAsset'))
        );
    }
}
