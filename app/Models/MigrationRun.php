<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConflictPolicy;
use App\Enums\MigrationRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * تشغيلة ترحيل ووردبريس واحدة: إعدادات اتصال المصدر + حقائق التدقيق +
 * عدّادات التقدّم المُجمَّعة + دورة الحياة. العمود db_password مُعمّى عبر cast.
 *
 * @property-read iterable<int,MigrationItem> $items
 * @property-read iterable<int,MigrationMedia> $media
 * @property-read iterable<int,MigrationCategoryMap> $categoryMaps
 */
class MigrationRun extends Model
{
    protected $table = 'wp_migration_runs';

    protected $fillable = [
        'name',
        'status',
        'conflict_policy',
        'db_host',
        'db_port',
        'db_name',
        'db_username',
        'db_password',
        'table_prefix',
        'uploads_path',
        'source_facts',
        'preview',
        'preview_generated_at',
        'mappings_updated_at',
        'approved_at',
        'total_items',
        'processed_items',
        'done_items',
        'partial_items',
        'failed_items',
        'skipped_items',
        'media_imported',
        'media_reused',
        'media_failed',
        'timeline',
        'started_at',
        'finished_at',
    ];

    protected $hidden = [
        'db_password',
    ];

    protected function casts(): array
    {
        return [
            'status' => MigrationRunStatus::class,
            'conflict_policy' => ConflictPolicy::class,
            'db_port' => 'integer',
            'db_password' => 'encrypted',
            'source_facts' => 'array',
            'preview' => 'array',
            'preview_generated_at' => 'datetime',
            'mappings_updated_at' => 'datetime',
            'approved_at' => 'datetime',
            'total_items' => 'integer',
            'processed_items' => 'integer',
            'done_items' => 'integer',
            'partial_items' => 'integer',
            'failed_items' => 'integer',
            'skipped_items' => 'integer',
            'media_imported' => 'integer',
            'media_reused' => 'integer',
            'media_failed' => 'integer',
            'timeline' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * يلحق حدثاً بالخطّ الزمنيّ ويُعيد المصفوفة الجديدة — للدمج في forceFill واحد
     * (started/paused/resumed/stopping/completed/retried). تتابع المُشغِّل/المُوزِّع
     * المفرد يجعل التزامن مهملاً عملياً.
     *
     * @return array<int,array{event:string,at:string}>
     */
    public function withEvent(string $event): array
    {
        $timeline = $this->timeline ?? [];
        $timeline[] = ['event' => $event, 'at' => now()->toISOString()];

        return $timeline;
    }

    /** المعاينة حالية (وُلِّدت بعد آخر تغيير في التنسيب). */
    public function previewIsCurrent(): bool
    {
        return $this->preview_generated_at !== null
            && ($this->mappings_updated_at === null
                || $this->preview_generated_at->greaterThanOrEqualTo($this->mappings_updated_at));
    }

    /** الاعتماد يخصّ المعاينة الحالية (أُقِرَّ بعد توليدها). */
    public function isApproved(): bool
    {
        return $this->approved_at !== null
            && $this->preview_generated_at !== null
            && $this->approved_at->greaterThanOrEqualTo($this->preview_generated_at);
    }

    /** بوّابة التنفيذ الصلبة: سياسة تعارض + معاينة حالية + اعتماد. */
    public function canExecute(): bool
    {
        return $this->conflict_policy !== null
            && $this->previewIsCurrent()
            && $this->isApproved();
    }

    /** @return HasMany<MigrationItem> */
    public function items(): HasMany
    {
        return $this->hasMany(MigrationItem::class, 'run_id');
    }

    /** @return HasMany<MigrationMedia> */
    public function media(): HasMany
    {
        return $this->hasMany(MigrationMedia::class, 'run_id');
    }

    /** @return HasMany<MigrationCategoryMap> */
    public function categoryMaps(): HasMany
    {
        return $this->hasMany(MigrationCategoryMap::class, 'run_id');
    }
}
