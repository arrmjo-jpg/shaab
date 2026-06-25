<?php

declare(strict_types=1);

namespace App\Actions\Public\Team;

use App\Http\Resources\Public\Team\PublicTeamMemberResource;
use App\Models\TeamMember;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Cache\TeamMemberCacheTags;
use App\Support\Content\TeamMemberRedirectResolver;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تفاصيل عضو فريق عام بالـ slug. النشِط فقط. كاش single-flight (CachedRead) ضدّ عاصفة
 * الطوابير. عند الغياب: محاولة 301 للـ slug القديم (resolver خالٍ من الحلقات) وإلا 404.
 */
class ShowPublicTeamMemberAction
{
    public function handle(string $slug): JsonResponse
    {
        $payload = CachedRead::remember(
            TeamMemberCacheTags::detailTags($slug),
            CacheKeys::publicTeamDetail($slug),
            CacheTtl::LONG,
            function () use ($slug): ?array {
                $member = TeamMember::query()
                    ->active()
                    ->with('avatarAsset')
                    ->where('slug', $slug)
                    ->first();

                return $member === null ? null : (new PublicTeamMemberResource($member))->resolve();
            }
        );

        if ($payload === null) {
            // SEO: slug قديم → 301 إلى رابط العضو الحالي (منع حلقة مضمون في الـ resolver).
            $target = TeamMemberRedirectResolver::resolveBySlug($slug);
            if ($target !== null) {
                $location = url("/api/v1/team/{$target->slug}");

                return new JsonResponse(['redirect' => $location], 301, ['Location' => $location]);
            }

            return ApiResponse::error(__('team.not_found'), [], 404);
        }

        return ApiResponse::success(data: $payload);
    }
}
