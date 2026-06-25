<?php

declare(strict_types=1);

namespace App\Actions\Public\Team;

use App\Models\TeamMember;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Cache\TeamMemberCacheTags;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * قائمة أعضاء الفريق النشِطين (قراءة عامة) — مرتّبة بـ sort_order ومُجمّعة حسب القسم
 * (مصوّرون/مبرمجون/مهندسون...). كاش single-flight (CachedRead) — نادرة التغيّر (TTL طويل).
 * بطاقات خفيفة (بلا حمولة SEO الثقيلة — تلك لصفحة التفاصيل).
 */
class ListPublicTeamMembersAction
{
    public function handle(): JsonResponse
    {
        $data = CachedRead::remember(
            TeamMemberCacheTags::feedTags(),
            CacheKeys::publicTeamList(),
            CacheTtl::LONG,
            function (): array {
                $members = TeamMember::query()
                    ->active()
                    ->with('avatarAsset')
                    ->ordered()
                    ->get();

                // تجميع حسب القسم مع الحفاظ على ترتيب الظهور (sort_order).
                $groups = [];
                foreach ($members as $member) {
                    $key = $member->department ?? '';
                    $groups[$key] ??= ['department' => $member->department, 'members' => []];
                    $groups[$key]['members'][] = self::card($member);
                }

                return array_values($groups);
            }
        );

        return ApiResponse::success(data: $data);
    }

    /** بطاقة عضو خفيفة للقائمة العامة (بلا حمولة SEO). */
    private static function card(TeamMember $member): array
    {
        $asset = $member->avatarAsset;

        return [
            'id' => $member->id,
            'name' => $member->name,
            'job_title' => $member->job_title,
            'department' => $member->department,
            'slug' => $member->slug,
            'avatar' => $asset ? [
                'url' => $asset->url(),
                'thumb' => $asset->conversionUrl('thumb'),
                'medium' => $asset->conversionUrl('medium'),
            ] : null,
            'social_links' => (object) ($member->social_links ?? []),
            'canonical_path' => $member->canonicalPath(),
        ];
    }
}
