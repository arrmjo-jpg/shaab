<?php

declare(strict_types=1);

namespace App\Support\Advertising;

use App\Enums\AdSelectorStrategy;
use App\Support\Advertising\Selectors\AdSelector;
use App\Support\Advertising\Selectors\EvenSelector;
use App\Support\Advertising\Selectors\RoundRobinSelector;
use App\Support\Advertising\Selectors\WeightedSelector;

/**
 * يحلّ استراتيجية المساحة إلى مُختار. قيمة غير معروفة ⇒ الموزون (افتراضيّ آمن).
 */
final class AdSelectorFactory
{
    public static function make(AdSelectorStrategy|string $strategy): AdSelector
    {
        $resolved = $strategy instanceof AdSelectorStrategy
            ? $strategy
            : (AdSelectorStrategy::tryFrom($strategy) ?? AdSelectorStrategy::Weighted);

        return match ($resolved) {
            AdSelectorStrategy::RoundRobin => new RoundRobinSelector,
            AdSelectorStrategy::Even => new EvenSelector,
            AdSelectorStrategy::Weighted => new WeightedSelector,
        };
    }
}
