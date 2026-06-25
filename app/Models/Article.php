<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Enums\LiveEventStatus;
use App\Support\Audit\AuditsChanges;
use App\Support\Content\SlugGenerator;
use App\Support\Engagement\HasEngagement;
use App\Support\Search\ResilientSearchable;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Tags\HasTags;

/**
 * كيان المحتوى الموحّد (ADR D1 — لا News منفصل).
 *
 * الوسائط عبر article_media pivot → MediaAsset (P9.2 B.2a).
 * أصل واحد مُشترَك بين مقالات متعدّدة؛ مجموعات: cover/gallery/inline/video.
 */
class Article extends Model
{
    use AuditsChanges;
    use HasEngagement;
    use HasTags;
    use ResilientSearchable;
    use Sluggable;
    use SoftDeletes;

    /** الحد الأقصى للتصنيفات الثانوية (ADR A3.2). */
    public const MAX_SECONDARY_CATEGORIES = 3;

    /** اللغات المدعومة — مرجع موحّد (ADR D2). */
    public const LOCALES = Category::LOCALES;

    protected string $auditLogName = 'article';

    /**
     * يُدقَّق التحوّل (لا المحتوى الطويل — تاريخه في article_revisions).
     *
     * @var array<int,string>
     */
    protected array $auditAttributes = [
        'type', 'status', 'event_status', 'title', 'subtitle', 'slug', 'locale',
        'primary_category_id', 'is_featured', 'is_breaking', 'is_pinned', 'is_header', 'is_editor_pick',
        'comments_enabled', 'published_at', 'seo_title', 'seo_description',
        'seo_keywords', 'canonical_url', 'robots', 'og_image_id',
    ];

    protected $fillable = [
        'author_id', 'published_by_id', 'primary_category_id',
        'type', 'status', 'event_status', 'locale', 'translation_group',
        'title', 'subtitle', 'slug', 'short_url', 'excerpt', 'content', 'content_json',
        'seo_title', 'seo_description', 'seo_keywords', 'canonical_url', 'robots', 'og_image_id',
        'is_featured', 'is_breaking', 'is_pinned', 'is_header', 'is_editor_pick', 'comments_enabled',
        'views_count', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ArticleType::class,
            'status' => ArticleStatus::class,
            'event_status' => LiveEventStatus::class,
            'content_json' => 'array',
            'is_featured' => 'boolean',
            'is_breaking' => 'boolean',
            'is_pinned' => 'boolean',
            'is_header' => 'boolean',
            'is_editor_pick' => 'boolean',
            'comments_enabled' => 'boolean',
            'published_at' => 'datetime',
            'views_count' => 'integer',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
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

    /**
     * مولّد slug يحافظ على الحروف العربية (لا transliteration) — مُوحَّد عبر
     * SlugGenerator (يعالج الترقيم والفواصل المكرّرة ويضمن قيمة غير فارغة).
     */
    public static function arabicSlug(string $string, string $separator): string
    {
        return SlugGenerator::makeWithFallback($string, $separator);
    }

    /**
     * فرادة الـ slug ضمن نفس اللغة فقط (ADR slug per-locale).
     */
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

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_id');
    }

    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    /** صورة المشاركة المخصّصة (og:image) من المكتبة المركزية. */
    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'og_image_id');
    }

    /** التصنيفات الثانوية (≤3) — الرئيسي ليس ضمن هذا الـ pivot. */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'article_category')
            ->withTimestamps();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ArticleRevision::class)->latest('created_at');
    }

    /** تحديثات التغطية الحيّة (P8) — تُستهلَك فقط لمقالات type=live. */
    public function liveUpdates(): HasMany
    {
        return $this->hasMany(ArticleLiveUpdate::class);
    }

    public function urlHistory(): HasMany
    {
        return $this->hasMany(ArticleUrlHistory::class);
    }

    /**
     * أصول المكتبة المركزية المرتبطة بالمقال (P9.2 B.2a).
     * مُرتَّبة حسب position ضمن كل مجموعة.
     */
    public function mediaAssets(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'article_media')
            ->withPivot(['collection', 'position'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    // ─── Scopes ─────────────────────────────────────────────────────

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    // ─── Scout / Meilisearch (بحث المقالات) ─────────────────────────

    public function searchableAs(): string
    {
        return 'articles_index';
    }

    /** يُفهرَس المنشور فقط (لا مسودّات/مجدوَل/مؤرشف) — صفر تسريب لغير المنشور. */
    public function shouldBeSearchable(): bool
    {
        return $this->status === ArticleStatus::Published
            && $this->published_at !== null
            && ! $this->published_at->isFuture();
    }

    /**
     * المستند المُفهرَس — يشمل النصّ الكامل (العنوان/الترويسة/المقتطف + متن HTML
     * مُجرَّد) والتصنيف والوسوم؛ وحقول تصفية/ترتيب (locale/type/published_at).
     *
     * @return array<string,mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['primaryCategory:id,name', 'tags']);

        return [
            'id' => (string) $this->id,
            'title' => (string) $this->title,
            'subtitle' => (string) $this->subtitle,
            'excerpt' => (string) $this->excerpt,
            'body' => trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $this->content))),
            'category' => (string) ($this->primaryCategory?->name ?? ''),
            'tags' => $this->tags->pluck('name')->implode(' '),
            // حقول تصفية/ترتيب (Meili filterable/sortable)
            'locale' => $this->locale,
            'type' => $this->type?->value, // null-safe: صفّ بنوع null لا يُسقط الفهرسة (scout:import 79k)
            'published_at' => $this->published_at?->getTimestamp(),
        ];
    }

    // ─── Canonical URL foundation (ADR A3.6) ────────────────────────

    /**
     * المسار القانوني الهجين المستقرّ: /{locale}/articles/{id}-{slug}
     *
     * يُفتَّت بالـ id لا بالـ slug (المعرّف لا يتغيّر، الـ slug تجميلي) — يتفادى
     * هشاشة المسارات عند تغيّر القسم أو الـ slug؛ الـ slug يبقى للقراءة وللـ 301.
     */
    public function canonicalPath(): string
    {
        return '/'.trim("{$this->locale}/articles/{$this->id}-{$this->slug}", '/');
    }

    /**
     * رابط صورة الكاتب (إن وُجدت) — يُستخدَم كبديل للغلاف في محتوى الرأي/المقال.
     * يتطلّب تحميل علاقة author مع عمود avatar.
     */
    public function authorAvatarUrl(): ?string
    {
        $avatar = $this->author?->avatar;

        return $avatar ? Storage::disk('public')->url($avatar) : null;
    }
}
