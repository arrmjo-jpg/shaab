<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * لقطة مقال غير قابلة للتعديل (append-only). لا updated_at.
 * تاريخ المحتوى الكامل يُحفظ هنا (لا في سجل التدقيق).
 */
class ArticleRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'article_id', 'editor_id', 'title', 'excerpt', 'content', 'content_json',
        'seo_title', 'seo_description', 'seo_keywords',
        'status_snapshot', 'flags_snapshot', 'tags_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'content_json' => 'array',
            'flags_snapshot' => 'array',
            'tags_snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }
}
