<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إشارة مرجعية لصفحة عدد لمستخدم مُصادَق. تشغيليّة (غير مُدقَّقة). الزوّار
 * يستخدمون localStorage.
 */
class EpaperBookmark extends Model
{
    protected $fillable = ['user_id', 'epaper_id', 'page_number'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'epaper_id' => 'integer',
            'page_number' => 'integer',
        ];
    }

    /** @return BelongsTo<Epaper, EpaperBookmark> */
    public function epaper(): BelongsTo
    {
        return $this->belongsTo(Epaper::class, 'epaper_id');
    }
}
