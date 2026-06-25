<?php

declare(strict_types=1);

namespace App\Support\Advertising;

use App\Enums\AdCreativeType;
use App\Enums\AdPlacementType;

/**
 * توافق الإسناد (config-time): أيّ أنواع إبداعات يقبلها كلّ نوع مساحة. قيد إعداديّ صريح
 * — منفصل عن قيود الخدمة (الأهليّة الزمنية/الجهاز تُفرَض في AdServer وقت العرض). المواضع
 * المرئيّة (banner/inline/sidebar/interstitial/overlay) تقبل صورة/HTML؛ preroll يقبل
 * video فقط (جاهز-مستقبلاً — وبما أنّ إبداعات الفيديو غير مُفعّلة الآن، يتعذّر إسناد preroll).
 */
final class AdPlacementCompatibility
{
    /** @return array<string, array<int,string>> placement_type ⇒ أنواع الإبداع المسموح بها */
    private static function matrix(): array
    {
        $visual = [AdCreativeType::Image->value, AdCreativeType::Html->value];

        return [
            AdPlacementType::Banner->value => $visual,
            AdPlacementType::Inline->value => $visual,
            AdPlacementType::Sidebar->value => $visual,
            AdPlacementType::Interstitial->value => $visual,
            AdPlacementType::Overlay->value => $visual,
            AdPlacementType::Preroll->value => [AdCreativeType::Video->value],
        ];
    }

    public static function isCompatible(AdPlacementType $zoneType, AdCreativeType $creativeType): bool
    {
        return in_array($creativeType->value, self::allowedCreativeTypes($zoneType), true);
    }

    /** @return array<int,string> */
    public static function allowedCreativeTypes(AdPlacementType $zoneType): array
    {
        return self::matrix()[$zoneType->value] ?? [];
    }
}
