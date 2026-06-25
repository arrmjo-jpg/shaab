<?php

declare(strict_types=1);

namespace App\Support\Analytics;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * نافذة زمنية للتحليلات (حبيبة يوميّة — مطابقة لتيليمتري content_daily_stats). تُحلّ من
 * فلتر مُعرَّف مسبقاً (24h/7d/30d) أو مخصّص (from/to) مع حدّ أقصى للطول وتطبيع آمن.
 * مشتركة بين تحليلات الفيديو والبثّ (مصدر حقيقة واحد للنطاق + مفتاح الكاش).
 */
final class AnalyticsRange
{
    /** @var array<string,int> أيام كل فلتر مُعرَّف مسبقاً. */
    private const PRESETS = ['24h' => 1, '7d' => 7, '30d' => 30];

    private const MAX_DAYS = 366;

    private function __construct(
        public readonly string $range,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    public static function resolve(?string $range, ?string $from = null, ?string $to = null): self
    {
        $range = $range !== null && $range !== '' ? $range : '30d';
        $today = CarbonImmutable::now()->startOfDay();

        if ($range === 'custom') {
            $start = (self::parse($from) ?? $today->subDays(29))->startOfDay();
            $end = (self::parse($to) ?? $today)->startOfDay();

            if ($end->lt($start)) {
                [$start, $end] = [$end, $start];
            }
            if ($end->gt($today)) {
                $end = $today;
            }
            if ($start->diffInDays($end) > self::MAX_DAYS) {
                $start = $end->subDays(self::MAX_DAYS);
            }

            return new self('custom', $start, $end);
        }

        $days = self::PRESETS[$range] ?? 30;
        $range = isset(self::PRESETS[$range]) ? $range : '30d';

        return new self($range, $today->subDays($days - 1), $today);
    }

    private static function parse(?string $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /** مفتاح مستقرّ لكاش التحليلات. */
    public function key(): string
    {
        return $this->range.':'.$this->from->toDateString().':'.$this->to->toDateString();
    }

    /** @return array{range:string,from:string,to:string,days:int} */
    public function toArray(): array
    {
        return [
            'range' => $this->range,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'days' => $this->days(),
        ];
    }
}
