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
 * تصنيف مكتبة الفيديو — تصنيف هرمي مستقل خاص بالفيديو (ليس تصنيفات الأخبار).
 * slug فريد لكل لغة، عربي-المحافظة. منع الأب-الذاتي/الدوري وحدّ العمق في الـ Action.
 */
class VideoCategory extends Model
{
    use AuditsChanges;
    use HasFactory;
    use Sluggable;
    use SoftDeletes;

    /** عمق أقصى ثابت معماري (مرآة Category). */
    public const MAX_DEPTH = 3;

    public const LOCALES = ['ar', 'en'];

    protected string $auditLogName = 'video_category';

    /** @var array<int,string> */
    protected array $auditAttributes = [
        'parent_id', 'locale', 'name', 'slug', 'description',
        'cover_media_id', 'is_active', 'sort_order', 'seo_title', 'seo_description',
    ];

    protected $fillable = [
        'parent_id', 'locale', 'translation_group', 'name', 'slug', 'description',
        'cover_media_id', 'is_active', 'sort_order', 'seo_title', 'seo_description',
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

    public function scopeWithUniqueSlugConstraints(
        Builder $query,
        Model $model,
        string $attribute,
        array $config,
        string $slug
    ): Builder {
        return $query->where('locale', $model->locale);
    }

    // ─── Relationships ──────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'video_category_id');
    }

    // ─── Scopes ─────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    // ─── Hierarchy helpers (used by VideoCategoryHierarchyGuard) ─────

    /** عمق العقدة (1 = جذر). يصعد سلسلة الآباء بحدّ أمان MAX_DEPTH+1. */
    public function depth(): int
    {
        $depth = 1;
        $node = $this;

        while ($node->parent_id !== null && $depth <= self::MAX_DEPTH + 1) {
            $node = $node->parent()->first();
            if ($node === null) {
                break;
            }
            $depth++;
        }

        return $depth;
    }

    /** أعمق امتداد للشجرة تحت هذه العقدة (1 = لا أبناء). */
    public function subtreeDepth(): int
    {
        $children = $this->children()->get();
        if ($children->isEmpty()) {
            return 1;
        }

        return 1 + $children->max(fn (self $c): int => $c->subtreeDepth());
    }

    /** هل $candidateId يقع ضمن نسل هذه العقدة (لمنع الدورات)؟ */
    public function isAncestorOf(int $candidateId): bool
    {
        foreach ($this->children()->get() as $child) {
            if ($child->id === $candidateId || $child->isAncestorOf($candidateId)) {
                return true;
            }
        }

        return false;
    }
}
