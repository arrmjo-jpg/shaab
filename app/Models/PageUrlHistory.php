<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تاريخ المسارات القانونية للصفحات الثابتة — التقاط canonical القديم عند تغيّر
 * slug/locale (للصفحات المنشورة سابقاً)، يُستهلَك من PageRedirectResolver لإعادة
 * توجيه 301. مرآة ReelUrlHistory / ArticleUrlHistory.
 */
class PageUrlHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'page_url_history';

    protected $fillable = [
        'page_id', 'locale', 'old_path', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
