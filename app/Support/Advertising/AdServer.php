<?php

declare(strict_types=1);

namespace App\Support\Advertising;

use App\Enums\AdCreativeType;
use App\Enums\AdSelectorStrategy;
use App\Models\AdPlacement;
use App\Models\AdZone;
use App\Support\Cache\AdCacheTags;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;

/**
 * محرّك خدمة الإعلانات (server-side). بِركة المرشّحين مُجزّأة بـ (zone, locale, device)
 * ومُكاشة بمصفوفات خفيفة (لا موديلات — لا فساد إلغاء-تسلسل). الاختيار حتميّ ضمن الدلو
 * الزمني الحالي عبر استراتيجية المساحة. لا استعلام قاعدة بيانات على المسار الساخن
 * عند إصابة الكاش؛ بناء البِركة محميّ بـ single-flight داخل CachedRead.
 */
final class AdServer
{
    /**
     * بِركة مساحة مُجزّأة ومُكاشة.
     *
     * @return array{strategy:string,candidates:array<int,array<string,mixed>>}
     */
    public static function pool(string $zoneKey, string $locale, string $device): array
    {
        $zoneKey = trim($zoneKey);
        if ($zoneKey === '') {
            return self::emptyPool();
        }

        /** @var array{strategy:string,candidates:array<int,array<string,mixed>>} */
        return CachedRead::remember(
            AdCacheTags::zoneTags($zoneKey),
            CacheKeys::adZonePool($zoneKey, $locale, $device),
            (int) config('advertising.serve.pool_ttl', CacheTtl::SHORT),
            fn (): array => self::build($zoneKey, $locale, $device),
        );
    }

    /**
     * يختار إبداعاً واحداً حتميّاً للدلو الزمني الحالي (أو دلو مُمرَّر — للاختبار).
     *
     * @return array<string,mixed>|null
     */
    public static function serve(string $zoneKey, string $locale, string $device, ?int $bucket = null): ?array
    {
        $pool = self::pool($zoneKey, $locale, $device);
        if ($pool['candidates'] === []) {
            return null;
        }

        $bucket ??= AdBucket::current();
        $seed = AdBucket::seed($zoneKey, $locale, $device, $bucket);

        return AdSelectorFactory::make($pool['strategy'])->select($pool['candidates'], $bucket, $seed);
    }

    /**
     * يبني البِركة من قاعدة البيانات: مساحة نشِطة + إسنادات نشِطة + إبداعات نشِطة قابلة
     * للخدمة الآن + حملات مؤهّلة (نشطة وضمن النافذة) + أهليّة الجهاز. مرتّبة بالوزن الفعّال.
     *
     * @return array{strategy:string,candidates:array<int,array<string,mixed>>}
     */
    private static function build(string $zoneKey, string $locale, string $device): array
    {
        $zone = AdZone::query()
            ->active()
            ->forLocale($locale)
            ->where('key', $zoneKey)
            ->first(['id', 'selector_strategy']);

        if ($zone === null) {
            return self::emptyPool();
        }

        $servableTypes = array_values(array_filter(
            AdCreativeType::values(),
            fn (string $type): bool => AdCreativeType::from($type)->isServableNow(),
        ));

        $placements = AdPlacement::query()
            ->where('ad_zone_id', $zone->id)
            ->where('is_active', true)
            ->whereHas('creative', function ($creative) use ($servableTypes): void {
                $creative->where('is_active', true)
                    ->whereIn('type', $servableTypes)
                    ->whereHas('campaign', fn ($campaign) => $campaign->servable());
            })
            ->with(['creative:id,type,weight'])
            ->get(['id', 'ad_creative_id', 'ad_zone_id', 'weight', 'device_targets']);

        $max = (int) config('advertising.serve.max_candidates_per_zone', 500);

        $candidates = $placements
            ->filter(fn (AdPlacement $p): bool => $p->eligibleForDevice($device))
            ->map(fn (AdPlacement $p): array => [
                'placement_id' => (int) $p->id,
                'creative_id' => (int) $p->ad_creative_id,
                'type' => $p->creative?->type->value,
                'weight' => $p->effectiveWeight(),
            ])
            ->sortByDesc('weight')
            ->take($max)
            ->values()
            ->all();

        return [
            'strategy' => $zone->selector_strategy->value,
            'candidates' => $candidates,
        ];
    }

    /** @return array{strategy:string,candidates:array<int,array<string,mixed>>} */
    private static function emptyPool(): array
    {
        return ['strategy' => AdSelectorStrategy::Weighted->value, 'candidates' => []];
    }
}
