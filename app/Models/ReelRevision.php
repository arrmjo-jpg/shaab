<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * لقطة ريل غير قابلة للتعديل (append-only). لا updated_at.
 */
class ReelRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'reel_id', 'editor_id', 'title', 'description',
        'seo_title', 'seo_description', 'seo_keywords',
        'status_snapshot', 'meta_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'meta_snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function reel(): BelongsTo
    {
        return $this->belongsTo(Reel::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }
}
