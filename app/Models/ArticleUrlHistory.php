<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تاريخ المسارات القانونية للمقال (ADR A4) — التقاط فقط في Wave C2.
 * resolver الـ 301 العام يُبنى في موجة الـ API العام اللاحقة.
 */
class ArticleUrlHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'article_url_history';

    protected $fillable = [
        'article_id', 'locale', 'old_path', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
