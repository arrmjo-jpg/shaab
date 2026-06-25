<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MigrationItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجلّ ترحيل منشور ووردبريس واحد → مقال AlphaCMS. هو عمود الاستئناف
 * والدّيدوب: المفتاح (run_id, wp_post_id) فريد، وكل خطوة لها طابع نقطة تحقّق.
 */
class MigrationItem extends Model
{
    protected $table = 'wp_migration_items';

    protected $fillable = [
        'run_id',
        'wp_post_id',
        'article_id',
        'status',
        'target_type',
        'source_title',
        'content_imported_at',
        'media_imported_at',
        'seo_imported_at',
        'redirects_created_at',
        'attempts',
        'last_step',
        'last_error',
        'content_checksum',
        'media_imported',
        'media_reused',
        'media_failed',
        'flags',
    ];

    protected function casts(): array
    {
        return [
            'status' => MigrationItemStatus::class,
            'wp_post_id' => 'integer',
            'article_id' => 'integer',
            'attempts' => 'integer',
            'media_imported' => 'integer',
            'media_reused' => 'integer',
            'media_failed' => 'integer',
            'flags' => 'array',
            'content_imported_at' => 'datetime',
            'media_imported_at' => 'datetime',
            'seo_imported_at' => 'datetime',
            'redirects_created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MigrationRun, MigrationItem> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(MigrationRun::class, 'run_id');
    }

    /** @return BelongsTo<Article, MigrationItem> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }
}
