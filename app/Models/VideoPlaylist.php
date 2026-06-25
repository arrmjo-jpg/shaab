<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Support\Audit\AuditsChanges;
use App\Support\Content\SlugGenerator;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * قائمة تشغيل فيديو من الدرجة الأولى — قائمة منسَّقة يدوياً مرتّبة (position).
 * تشارك دورة الحياة/الرؤية/SEO مع الفيديو. سجلّ مسارات قديمة خاص بها
 * (playlist_url_history) لإعادة التوجيه 301 عند تغيّر slug/locale — كالفيديو تماماً.
 */
class VideoPlaylist extends Model
{
    use AuditsChanges;
    use HasFactory;
    use Sluggable;
    use SoftDeletes;

    public const LOCALES = ['ar', 'en'];

    protected string $auditLogName = 'video_playlist';

    /** @var array<int,string> */
    protected array $auditAttributes = [
        'status', 'visibility', 'is_featured', 'title', 'slug', 'locale',
        'cover_media_id', 'sort_order', 'published_at',
        'seo_title', 'seo_description', 'seo_keywords', 'canonical_url', 'robots',
    ];

    protected $fillable = [
        'uuid', 'author_id', 'locale', 'translation_group', 'title', 'slug', 'description',
        'cover_media_id', 'status', 'visibility', 'is_featured', 'sort_order',
        'seo_title', 'seo_description', 'seo_keywords', 'canonical_url', 'robots', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VideoStatus::class,
            'visibility' => VideoVisibility::class,
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $playlist): void {
            if (empty($playlist->uuid)) {
                $playlist->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return array<string, array<string, mixed>> */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
                'unique' => true,
                'includeTrashed' => true,
                'maxLength' => 190,
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

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'playlist_video')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('playlist_video.position');
    }

    public function urlHistory(): HasMany
    {
        return $this->hasMany(PlaylistUrlHistory::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────────

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', VideoStatus::Published->value)
            ->where('published_at', '<=', now());
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->published()->where('visibility', VideoVisibility::Public->value);
    }

    /**
     * قابلة للعرض عبر رابط مباشر: منشورة + رؤية عامة أو غير مُدرَجة. تستبعد
     * المسودة/المؤرشف/الخاص — مخصّصة لنقطة التفاصيل بالـ slug (القوائم تستخدم public()).
     */
    public function scopeViewable(Builder $query): Builder
    {
        return $query->published()->whereIn('visibility', [
            VideoVisibility::Public->value,
            VideoVisibility::Unlisted->value,
        ]);
    }

    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    /** المسار القانوني المستقرّ: /{locale}/playlists/{id}-{slug}. */
    public function canonicalPath(): string
    {
        return '/'.trim("{$this->locale}/playlists/{$this->id}-{$this->slug}", '/');
    }
}
