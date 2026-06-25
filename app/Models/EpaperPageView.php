<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** عدّاد مشاهدات صفحة عدد (مجمَّع) — أساس «أكثر الصفحات مشاهدةً». */
class EpaperPageView extends Model
{
    protected $fillable = ['epaper_id', 'page_number', 'views'];

    protected function casts(): array
    {
        return [
            'epaper_id' => 'integer',
            'page_number' => 'integer',
            'views' => 'integer',
        ];
    }

    /** @return BelongsTo<Epaper, EpaperPageView> */
    public function epaper(): BelongsTo
    {
        return $this->belongsTo(Epaper::class, 'epaper_id');
    }
}
