<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * عدّاد أداء ساخن مُجمَّع لكل إسناد (placement) — انطباعات/نقرات حيّة. يُغذّى من
 * AdEventBuffer::flush (زيادة ذرّية مُجمَّعة). بلا FK (مرآة EngagementCounter).
 */
class AdCounter extends Model
{
    protected $fillable = [
        'ad_placement_id', 'impressions', 'clicks',
    ];

    protected function casts(): array
    {
        return [
            'ad_placement_id' => 'integer',
            'impressions' => 'integer',
            'clicks' => 'integer',
        ];
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(AdPlacement::class, 'ad_placement_id');
    }
}
