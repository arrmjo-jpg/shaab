<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WpCategoryDisposition;
use App\Enums\WpCategoryMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تنسيب تصنيف ووردبريس مختار → مجمّعات تصنيفات AlphaCMS (أخبار/مقالات).
 * يحدّد المُشغِّل الوضع والهدف؛ النوع المحسوم للمنشور يُشتقّ منه في طور التحويل.
 */
class MigrationCategoryMap extends Model
{
    protected $table = 'wp_migration_category_maps';

    protected $fillable = [
        'run_id',
        'wp_term_id',
        'wp_name',
        'wp_slug',
        'wp_parent_id',
        'wp_count',
        'mode',
        'disposition',
        'target_category_id',
        'created_category_id',
    ];

    protected function casts(): array
    {
        return [
            'mode' => WpCategoryMode::class,
            'disposition' => WpCategoryDisposition::class,
            'wp_term_id' => 'integer',
            'wp_parent_id' => 'integer',
            'wp_count' => 'integer',
            'target_category_id' => 'integer',
            'created_category_id' => 'integer',
        ];
    }

    /** @return BelongsTo<MigrationRun, MigrationCategoryMap> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(MigrationRun::class, 'run_id');
    }

    /** @return BelongsTo<Category, MigrationCategoryMap> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'target_category_id');
    }

    /** التصنيف المُنشأ ترحيلياً لهذا الصفّ (disposition=create) — للتتبّع والاسترجاع. */
    public function createdCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'created_category_id');
    }
}
