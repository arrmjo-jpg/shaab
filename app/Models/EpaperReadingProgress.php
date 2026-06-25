<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * متابعة قراءة عدد لمستخدم مُصادَق (آخر صفحة). تشغيليّة (غير مُدقَّقة). الزوّار
 * يستخدمون localStorage بدل هذا الصفّ.
 */
class EpaperReadingProgress extends Model
{
    protected $table = 'epaper_reading_progress';

    protected $fillable = ['user_id', 'epaper_id', 'last_page'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'epaper_id' => 'integer',
            'last_page' => 'integer',
        ];
    }

    /** @return BelongsTo<Epaper, EpaperReadingProgress> */
    public function epaper(): BelongsTo
    {
        return $this->belongsTo(Epaper::class, 'epaper_id');
    }
}
