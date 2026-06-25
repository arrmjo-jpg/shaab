<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Audit\AuditsChanges;
use App\Support\Content\SlugGenerator;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * تصنيف البثّ — تصنيف مسطّح (FLAT) مستقل خاص بنطاق البثّ. عربي فقط؛ slug فريد عام
 * (لا بُعد لغة). يعيد استخدام أنماط VideoCategory دون الهرمية/التعدّد اللغوي.
 */
class BroadcastCategory extends Model
{
    use AuditsChanges;
    use HasFactory;
    use Sluggable;
    use SoftDeletes;

    protected string $auditLogName = 'broadcast_category';

    /** @var array<int,string> */
    protected array $auditAttributes = [
        'name', 'slug', 'description', 'cover_media_id', 'is_active', 'sort_order',
        'seo_title', 'seo_description',
    ];

    protected $fillable = [
        'name', 'slug', 'description', 'cover_media_id', 'is_active', 'sort_order',
        'seo_title', 'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
                'unique' => true,
                'includeTrashed' => true,
                'maxLength' => 160,
                'method' => [self::class, 'arabicSlug'],
            ],
        ];
    }

    public static function arabicSlug(string $string, string $separator): string
    {
        return SlugGenerator::makeWithFallback($string, $separator);
    }

    // ─── Relationships ──────────────────────────────────────────────

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    public function broadcasts(): HasMany
    {
        return $this->hasMany(Broadcast::class, 'category_id');
    }

    // ─── Scopes ─────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
