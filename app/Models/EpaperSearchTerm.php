<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** عدّاد عبارة بحث داخل عدد (مجمَّع) — أساس «أكثر عبارات البحث استخداماً». */
class EpaperSearchTerm extends Model
{
    protected $fillable = ['epaper_id', 'term', 'count'];

    protected function casts(): array
    {
        return [
            'epaper_id' => 'integer',
            'count' => 'integer',
        ];
    }

    /** @return BelongsTo<Epaper, EpaperSearchTerm> */
    public function epaper(): BelongsTo
    {
        return $this->belongsTo(Epaper::class, 'epaper_id');
    }
}
