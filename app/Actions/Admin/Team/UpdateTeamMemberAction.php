<?php

declare(strict_types=1);

namespace App\Actions\Admin\Team;

use App\Enums\TeamMemberStatus;
use App\Http\Resources\Admin\Team\TeamMemberResource;
use App\Models\TeamMember;
use App\Models\TeamMemberUrlHistory;
use App\Support\Cache\TeamMemberCacheTags;
use App\Support\Content\PageContentSanitizer;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UpdateTeamMemberAction
{
    /** الحقول القابلة للتعديل المباشر (الحالة عبر مسار التبديل فقط). */
    private const FIELDS = [
        'name', 'job_title', 'department', 'avatar_asset_id',
        'seo_title', 'seo_description', 'seo_keywords', 'canonical_url', 'robots',
        'sort_order',
    ];

    public function handle(TeamMember $member, array $validated): JsonResponse
    {
        // التُقط قبل التعديل لالتقاط حافة الـ slug القديم عند تغيّره.
        $oldPath = $member->canonicalPath();
        $oldSlug = (string) $member->slug;
        $wasActive = $member->status === TeamMemberStatus::Active;

        $member = DB::transaction(function () use ($member, $validated, $oldPath, $wasActive): TeamMember {
            foreach (self::FIELDS as $field) {
                if (array_key_exists($field, $validated)) {
                    $member->{$field} = $validated[$field];
                }
            }

            if (array_key_exists('bio', $validated)) {
                $member->bio = PageContentSanitizer::sanitize($validated['bio']);
            }

            if (array_key_exists('social_links', $validated)) {
                $member->social_links = TeamMember::sanitizeSocialLinks($validated['social_links']);
            }

            if (! empty($validated['slug'])) {
                $member->slug = $validated['slug'];
            }

            $member->save();

            // التقط المسار القانوني القديم عند تغيّره (لإعادة توجيه 301) — للأعضاء
            // النشِطين فقط: الـ slugs غير النشِطة لم تُعرَض للعامّة، فلا قيمة SEO لحفظها.
            // firstOrCreate يمنع التكرار عبر القيد الفريد على old_path.
            $newPath = $member->fresh()->canonicalPath();
            if ($newPath !== $oldPath && $wasActive) {
                TeamMemberUrlHistory::firstOrCreate(
                    ['old_path' => $oldPath],
                    ['team_member_id' => $member->id, 'reason' => 'canonical_change'],
                );
            }

            return $member;
        });

        Cache::tags(TeamMemberCacheTags::invalidationTags($member->fresh(), $oldSlug))->flush();

        return ApiResponse::success(
            __('team.updated'),
            new TeamMemberResource($member->fresh()->load('avatarAsset'))
        );
    }
}
