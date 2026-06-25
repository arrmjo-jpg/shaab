<?php

declare(strict_types=1);

namespace App\Support\Cache;

use App\Models\TeamMember;

/**
 * وسوم كاش أعضاء الفريق — مصدر الحقيقة لاستراتيجية الإبطال الحبيبي. مرآةُ
 * PageCacheTags لكن بلا locale (نطاق عربيّ أحادي):
 *
 *   ALL            → مظلّة عامة (تفريغ شامل يدوي/صيانة).
 *   FEED           → قائمة الفريق العامة (مُجمّعة حسب القسم).
 *   detail(slug)   → صفحة عضو واحد (مفتاح الكاش بالـ slug).
 *
 * قاعدة الإبطال عند كتابة عضو: flush(detail(slug)) + flush(FEED). عند تغيّر الـ
 * slug: نُبطِل القديم والجديد معاً.
 */
final class TeamMemberCacheTags
{
    public const ALL = 'team-members';

    public const FEED = 'team-members:feed';

    /** وسم تفاصيل عضو واحد (بالـ slug — مطابق لمفتاح الكاش). */
    public static function detail(string $slug): string
    {
        return 'team-members:detail:'.$slug;
    }

    /**
     * وسوم القائمة العامة.
     *
     * @return array<int,string>
     */
    public static function feedTags(): array
    {
        return [self::ALL, self::FEED];
    }

    /**
     * وسوم تفاصيل عضو (مظلّة + تفاصيله فقط — بلا وسم القائمة عمداً، للعزل).
     *
     * @return array<int,string>
     */
    public static function detailTags(string $slug): array
    {
        return [self::ALL, self::detail($slug)];
    }

    /**
     * الوسوم الواجب إبطالها عند كتابة/تحوّل عضو: القائمة + تفاصيله؛ وعند تغيّر الـ
     * slug يشمل القديم أيضاً (يمنع بقايا قديمة).
     *
     * @return array<int,string>
     */
    public static function invalidationTags(TeamMember $member, ?string $oldSlug = null): array
    {
        $tags = [self::FEED, self::detail((string) $member->slug)];

        $oldSlug ??= (string) $member->slug;
        if ($oldSlug !== (string) $member->slug) {
            $tags[] = self::detail($oldSlug);
        }

        return array_values(array_unique($tags));
    }
}
