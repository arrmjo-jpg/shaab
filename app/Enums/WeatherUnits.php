<?php

declare(strict_types=1);

namespace App\Enums;

enum WeatherUnits: string
{
    case Standard = 'standard';
    case Metric = 'metric';
    case Imperial = 'imperial';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'قياسي (كلفن)',
            self::Metric => 'متري (مئوية)',
            self::Imperial => 'إمبراطوري (فهرنهايت)',
        };
    }
}
