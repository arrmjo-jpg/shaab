<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * عدّاد استخدام بحث الأرشيف العابر اليوميّ (تاريخ × لغة) — مقياس مجمَّع بحت بلا أيّ
 * هوية/استعلام مخزَّن (واعٍ للخصوصية). يُغذّى من RecordEpaperArchiveSearchJob.
 */
class EpaperArchiveSearchDaily extends Model
{
    protected $table = 'epaper_archive_search_daily';

    protected $fillable = ['stat_date', 'locale', 'count'];

    protected function casts(): array
    {
        // stat_date نصّ ISO 'Y-m-d' (لا cast) — مطابقة firstOrCreate دقيقة + مدى لفظيّ.
        return [
            'count' => 'integer',
        ];
    }
}
