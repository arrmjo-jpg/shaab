<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PageStatus;
use App\Support\Audit\AuditsChanges;
use App\Support\Content\SlugGenerator;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * صفحة ثابتة — نوع محتوى مستقلّ بذاته (من نحن/الخصوصية/الاستخدام/الشروط/أعلن معنا...).
 *
 * تُدار من لوحة الإدارة (بدل client.json). مستقلّة: لا تصنيفات ولا وسائط ولا تفاعل.
 * تعيد استخدام بنية AlphaCMS: هوية موحّدة (users)، أعمدة SEO أصلية، slug عربيّ
 * فريد لكل لغة، وتدقيق موحّد عبر AuditsChanges (نظام spatie/activitylog الوحيد).
 */
class Page extends Model
{
    use AuditsChanges;
    use Sluggable;
    use SoftDeletes;

    /** اللغات المدعومة — معرّفة محلياً (نطاق مستقلّ). */
    public const LOCALES = ['ar', 'en'];

    protected string $auditLogName = 'page';

    /** @var array<int,string> المحتوى الطويل لا يُدقَّق (مطابق لاستثناء المقال/الريل للمحتوى). */
    protected array $auditAttributes = [
        'status', 'locale', 'title', 'slug', 'show_in_header', 'show_in_footer',
        'sort_order', 'template', 'published_at', 'seo_title', 'seo_description',
        'seo_keywords', 'canonical_url', 'robots', 'author_id', 'published_by_id',
    ];

    protected $fillable = [
        'uuid', 'author_id', 'published_by_id', 'status', 'locale', 'translation_group',
        'title', 'slug', 'content', 'seo_title', 'seo_description', 'seo_keywords',
        'canonical_url', 'robots', 'template', 'show_in_header', 'show_in_footer',
        'sort_order', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'show_in_header' => 'boolean',
            'show_in_footer' => 'boolean',
            'sort_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $page): void {
            if (empty($page->uuid)) {
                $page->uuid = (string) Str::uuid();
            }
        });
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

    /** مولّد slug يحافظ على الحروف العربية — مُوحَّد عبر SlugGenerator. */
    public static function arabicSlug(string $string, string $separator): string
    {
        return SlugGenerator::makeWithFallback($string, $separator);
    }

    /** فرادة الـ slug ضمن نفس اللغة فقط (slug per-locale). */
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

    // ─── Scopes ─────────────────────────────────────────────────────

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PageStatus::Published->value)
            ->where('published_at', '<=', now());
    }

    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    // ─── SEO / sharing (نفس نمط المقال/الريل) ───────────────────────

    /**
     * المسار القانوني المستقرّ: /{locale}/pages/{slug}. الصفحات الثابتة لها slug
     * بشريّ مستقرّ (لا تفتيت بالـ id مثل الريل/المقال) — أنسب لروابط دائمة للـ SEO.
     */
    public function canonicalPath(): string
    {
        return '/'.trim("{$this->locale}/pages/{$this->slug}", '/');
    }
}
