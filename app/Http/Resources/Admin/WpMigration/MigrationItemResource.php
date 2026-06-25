<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\WpMigration;

use App\Models\MigrationItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * عنصر دفتر للتنقيب في الفشل (#4): الهوية المصدرية + سبب الفشل المُصنَّف + المحاولات
 * + الطوابع لكل خطوة + سياق الخطأ التفصيليّ + عدّادات الوسائط + التحذيرات.
 *
 * @mixin MigrationItem
 */
class MigrationItemResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wp_post_id' => $this->wp_post_id,
            'source_title' => $this->source_title,
            'status' => $this->status->value,
            'target_type' => $this->target_type,
            'article_id' => $this->article_id,
            'failure_reason' => data_get($this->flags, 'reason'),
            'warnings' => data_get($this->flags, 'warnings', []),
            'attempts' => $this->attempts,
            'last_step' => $this->last_step,
            'last_error' => $this->last_error,
            'media' => [
                'imported' => $this->media_imported,
                'reused' => $this->media_reused,
                'failed' => $this->media_failed,
            ],
            'checkpoints' => [
                'content_imported_at' => $this->content_imported_at?->toISOString(),
                'media_imported_at' => $this->media_imported_at?->toISOString(),
                'seo_imported_at' => $this->seo_imported_at?->toISOString(),
                'redirects_created_at' => $this->redirects_created_at?->toISOString(),
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
