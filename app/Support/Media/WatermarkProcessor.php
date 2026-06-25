<?php

declare(strict_types=1);

namespace App\Support\Media;

use Spatie\Image\Enums\AlignPosition;
use Spatie\Image\Enums\Unit;
use Spatie\Image\Image;

/**
 * معالج العلامة المائية — يحترم إعدادات لوحة الإدارة بالكامل
 * (الموضع/العتامة/العرض/الهامش). لا يمسّ الأصل؛ يكتب نسخة مُعلَّمة.
 */
final class WatermarkProcessor
{
    /**
     * يطبّق العلامة المائية على صورة ويحفظها في المسار الهدف.
     */
    public function apply(
        string $sourcePath,
        string $watermarkPath,
        string $destinationPath,
        int $opacity = 80,
        int $width = 100,
        int $margin = 20,
        string $position = 'bottom-left',
    ): void {
        Image::load($sourcePath)
            ->watermark(
                $watermarkPath,
                position: self::alignPosition($position),
                paddingX: $margin,
                paddingY: $margin,
                paddingUnit: Unit::Pixel,
                width: $width,
                widthUnit: Unit::Pixel,
                alpha: $opacity,
            )
            ->save($destinationPath);
    }

    /** يربط موضع الإعدادات (WatermarkPosition) بموضع Spatie. */
    private static function alignPosition(string $position): AlignPosition
    {
        return match ($position) {
            'top-left' => AlignPosition::TopLeft,
            'top-right' => AlignPosition::TopRight,
            'bottom-right' => AlignPosition::BottomRight,
            'center' => AlignPosition::Center,
            default => AlignPosition::BottomLeft,
        };
    }
}
