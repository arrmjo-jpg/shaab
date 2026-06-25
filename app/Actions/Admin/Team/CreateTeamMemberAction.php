<?php

declare(strict_types=1);

namespace App\Actions\Admin\Team;

use App\Enums\TeamMemberStatus;
use App\Http\Resources\Admin\Team\TeamMemberResource;
use App\Models\TeamMember;
use App\Support\Cache\TeamMemberCacheTags;
use App\Support\Content\PageContentSanitizer;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * إنشاء عضو فريق. الـ bio يُنقّى بقائمة بيضاء (PageContentSanitizer — مُعاد استخدامه،
 * لا مصفاة موازية) على مسار الكتابة. روابط التواصل تُنقّى لمفاتيحها المسموح بها.
 */
class CreateTeamMemberAction
{
    public function handle(array $validated): JsonResponse
    {
        $member = DB::transaction(function () use ($validated): TeamMember {
            $member = new TeamMember;
            $member->fill([
                'name' => $validated['name'],
                'job_title' => $validated['job_title'],
                'department' => $validated['department'] ?? null,
                'bio' => PageContentSanitizer::sanitize($validated['bio'] ?? null),
                'avatar_asset_id' => $validated['avatar_asset_id'] ?? null,
                'social_links' => TeamMember::sanitizeSocialLinks($validated['social_links'] ?? null),
                'seo_title' => $validated['seo_title'] ?? null,
                'seo_description' => $validated['seo_description'] ?? null,
                'seo_keywords' => $validated['seo_keywords'] ?? null,
                'canonical_url' => $validated['canonical_url'] ?? null,
                'robots' => $validated['robots'] ?? null,
                'status' => $validated['status'] ?? TeamMemberStatus::Active->value,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            if (! empty($validated['slug'])) {
                $member->slug = $validated['slug'];
            }

            $member->save();

            return $member;
        });

        Cache::tags(TeamMemberCacheTags::invalidationTags($member))->flush();

        return ApiResponse::success(
            __('team.created'),
            new TeamMemberResource($member->load('avatarAsset')),
            201
        );
    }
}
