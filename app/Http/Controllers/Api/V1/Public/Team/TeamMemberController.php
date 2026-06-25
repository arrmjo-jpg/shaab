<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Team;

use App\Actions\Public\Team\ListPublicTeamMembersAction;
use App\Actions\Public\Team\ShowPublicTeamMemberAction;
use App\Http\Controllers\Controller;
use App\Support\Content\PublicSeoBuilder;
use App\Support\Content\TeamMemberRedirectResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * الواجهة العامة لأعضاء الفريق — نطاق عربيّ أحادي (بلا بادئة locale): /api/v1/team.
 */
class TeamMemberController extends Controller
{
    /** قائمة الأعضاء النشِطين مُجمّعة حسب القسم. */
    public function index(): JsonResponse
    {
        return (new ListPublicTeamMembersAction)->handle();
    }

    /** تفاصيل عضو نشِط بالـ slug (+ حمولة Person JSON-LD). */
    public function show(string $slug): JsonResponse
    {
        return (new ShowPublicTeamMemberAction)->handle($slug);
    }

    /**
     * مُحلِّل إعادة التوجيه 301: مسار قانوني قديم كامل (?path=) → الـ canonical الحالي.
     * يستخدمه catch-all الواجهة/الزواحف للروابط القديمة. لا تطابق ⇒ 404.
     */
    public function redirect(Request $request): JsonResponse
    {
        $path = (string) $request->query('path', '');
        $target = $path !== '' ? TeamMemberRedirectResolver::resolveByPath($path) : null;

        if ($target === null) {
            return response()->json(['message' => __('team.not_found')], 404);
        }

        $location = PublicSeoBuilder::absoluteUrl($target->canonicalPath());

        return new JsonResponse(['redirect' => $location], 301, ['Location' => $location]);
    }
}
