<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة نشاط عضو الفريق: نشِط/غير نشِط. لا دورة حياة تحريرية (لا مسودّة/أرشفة) —
 * مجرّد إظهار/إخفاء على الموقع. التبديل عبر ToggleTeamMemberStatusAction (بوابة
 * team.edit). غير النشِط يختفي من العرض العام ويُرجِع 404 (Slice 4).
 */
enum TeamMemberStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return __('team.status.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
