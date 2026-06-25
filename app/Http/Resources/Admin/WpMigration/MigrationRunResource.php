<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\WpMigration;

use App\Models\MigrationRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MigrationRun
 */
class MigrationRunResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status->value,
            'conflict_policy' => $this->conflict_policy?->value,
            // إعدادات الاتصال بلا كلمة مرور إطلاقاً.
            'connection' => [
                'db_host' => $this->db_host,
                'db_port' => $this->db_port,
                'db_name' => $this->db_name,
                'db_username' => $this->db_username,
                'table_prefix' => $this->table_prefix,
                'uploads_path' => $this->uploads_path,
            ],
            'source_facts' => $this->source_facts,
            'preview' => $this->preview,
            'preview_generated_at' => $this->preview_generated_at?->toISOString(),
            'mappings_updated_at' => $this->mappings_updated_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            // حالة بوّابة التنفيذ (مشتقّة) — لتعطيل/تمكين الواجهة.
            'preview_stale' => ! $this->previewIsCurrent(),
            'approved' => $this->isApproved(),
            'can_execute' => $this->canExecute(),
            // ⚠️ TEMPORARY FEATURE — Quick Incremental Import — Remove before Production release.
            // TODO(production): احذف هذا العلم (يتحكّم بإظهار الزرّ) عند إزالة الاختصار.
            'quick_incremental_enabled' => (bool) config('wp-migration.quick_incremental', false),
            'progress' => [
                'total' => $this->total_items,
                'processed' => $this->processed_items,
                'done' => $this->done_items,
                'partial' => $this->partial_items,
                'failed' => $this->failed_items,
                'skipped' => $this->skipped_items,
                'media_imported' => $this->media_imported,
                'media_reused' => $this->media_reused,
                'media_failed' => $this->media_failed,
            ],
            'timeline' => $this->timeline ?? [],
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
