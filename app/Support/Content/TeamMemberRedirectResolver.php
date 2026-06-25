<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Models\TeamMember;
use App\Models\TeamMemberUrlHistory;

/**
 * مُحلِّل إعادة التوجيه 301 لأعضاء الفريق (مرآة PageRedirectResolver، نطاق عربيّ
 * أحادي بلا locale). يربط مساراً/سلَغاً قديماً بالعضو النشِط الحالي.
 *
 * منع الحلقات (safeguard): التصميم يخزّن old_path → team_member_id مباشرةً (لا
 * سلسلة old→new)، والـ canonical يُشتقّ دائماً من الـ slug الحالي للعضو — فالحلّ
 * O(1) خالٍ من الحلقات بنيوياً (لا A→B→C→A ممكنة). يكفي حارس self-reference:
 * لا توجيه إن طابق الـ canonical الحالي المسارَ المطلوب (يمنع A→A)، ولا توجيه إن
 * لم يَعُد العضو نشِطاً.
 */
final class TeamMemberRedirectResolver
{
    /** مطابقة مسار قانوني قديم كامل (/team/{slug}) — مفهرس O(1). */
    public static function resolveByPath(string $oldPath): ?TeamMember
    {
        $oldPath = '/'.trim($oldPath, '/');

        $row = TeamMemberUrlHistory::query()
            ->where('old_path', $oldPath)
            ->latest('id')
            ->first();

        if ($row === null) {
            return null;
        }

        $member = TeamMember::query()->active()->whereKey($row->team_member_id)->first();
        if ($member === null) {
            return null;
        }

        // حارس self-reference: لا تُعِد التوجيه إن كان canonical الحالي مطابقاً للمطلوب.
        return $member->canonicalPath() === $oldPath ? null : $member;
    }

    /** مطابقة بالـ slug — لاستهلاك نقطة /team/{slug} عند الـ 404 (Slice 4). */
    public static function resolveBySlug(string $slug): ?TeamMember
    {
        if ($slug === '') {
            return null;
        }

        $candidatePath = '/'.trim("team/{$slug}", '/');

        return self::resolveByPath($candidatePath);
    }
}
