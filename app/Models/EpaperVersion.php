<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نسخة من عدد رقميّ (سجلّ استبدال الـ PDF) — تحفظ الأصل السابق وملاحظة ومُنفِّذها.
 */
class EpaperVersion extends Model
{
    protected $fillable = [
        'epaper_id',
        'version',
        'media_asset_id',
        'page_count',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'page_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Epaper, EpaperVersion> */
    public function epaper(): BelongsTo
    {
        return $this->belongsTo(Epaper::class, 'epaper_id');
    }

    /** @return BelongsTo<MediaAsset, EpaperVersion> */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}
