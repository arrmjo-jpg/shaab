<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Models\Reel;
use App\Models\ReelRevision;

/**
 * مُسجِّل لقطات الريل — يُستدعى صراحةً من الـ Action بعد كل كتابة (لا observer).
 */
final class ReelRevisionRecorder
{
    public static function snapshot(Reel $reel, ?int $editorId): void
    {
        ReelRevision::create([
            'reel_id' => $reel->id,
            'editor_id' => $editorId,
            'title' => $reel->title,
            'description' => $reel->description,
            'seo_title' => $reel->seo_title,
            'seo_description' => $reel->seo_description,
            'seo_keywords' => $reel->seo_keywords,
            'status_snapshot' => $reel->status->value,
            'meta_snapshot' => [
                'media_asset_id' => $reel->media_asset_id,
                'duration_seconds' => $reel->duration_seconds,
                'sort_order' => $reel->sort_order,
            ],
        ]);
    }
}
