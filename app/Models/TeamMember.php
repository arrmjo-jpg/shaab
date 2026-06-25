<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TeamMemberStatus;
use App\Support\Audit\AuditsChanges;
use App\Support\Content\SlugGenerator;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * عضو فريق العمل — صفحة تعريفية بشخص ضمن الفريق (مصوّر/مبرمج/مهندس...). محتوى
 * تعريفيّ مستقلّ تماماً (لا ربط بـ users، لا دخول للوحة). نطاق عربيّ أحادي:
 * canonical = /team/{slug} (بلا locale prefix).
 *
 * يعيد استخدام بنية AlphaCMS: uuid عام، أعمدة SEO أصلية، slug عربيّ فريد عالمياً،
 * تدقيق موحّد عبر AuditsChanges (نظام spatie/activitylog الوحيد).
 */
class TeamMember extends Model
{
    use AuditsChanges;
    use Sluggable;
    use SoftDeletes;

    /** مفاتيح روابط التواصل المسموح بها — مصدر الحقيقة للتحقّق والتنقية. */
    public const SOCIAL_KEYS = [
        'facebook', 'twitter_x', 'instagram', 'tiktok', 'linkedin', 'youtube', 'website',
    ];

    protected string $auditLogName = 'team_member';

    /** @var array<int,string> المحتوى الطويل (bio) لا يُدقَّق (مطابق لاستثناء المقال/الصفحة). */
    protected array $auditAttributes = [
        'name', 'job_title', 'department', 'slug', 'status', 'sort_order',
        'avatar_asset_id', 'seo_title', 'seo_description', 'seo_keywords', 'canonical_url', 'robots',
    ];

    protected $fillable = [
        'uuid', 'name', 'job_title', 'department', 'slug', 'bio', 'avatar_asset_id',
        'social_links', 'seo_title', 'seo_description', 'seo_keywords',
        'canonical_url', 'robots', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => TeamMemberStatus::class,
            'social_links' => 'array',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $member): void {
            if (empty($member->uuid)) {
                $member->uuid = (string) Str::uuid();
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
                'source' => 'name',
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

    /**
     * تنقية روابط التواصل: إبقاء المفاتيح المسموح بها فقط مع قيم غير فارغة.
     * مصدر موحّد يستخدمه إنشاء/تعديل العضو (لا تكرار منطق).
     *
     * @param  array<string,mixed>|null  $links
     * @return array<string,string>|null
     */
    public static function sanitizeSocialLinks(?array $links): ?array
    {
        if ($links === null) {
            return null;
        }

        $clean = [];
        foreach (self::SOCIAL_KEYS as $key) {
            $value = isset($links[$key]) ? trim((string) $links[$key]) : '';
            if ($value !== '') {
                $clean[$key] = $value;
            }
        }

        return $clean === [] ? null : $clean;
    }

    // ─── Relationships ──────────────────────────────────────────────

    public function urlHistory(): HasMany
    {
        return $this->hasMany(TeamMemberUrlHistory::class);
    }

    /** الصورة الشخصية من المكتبة المركزية (MediaAsset) — CDN/conversions/حوكمة. */
    public function avatarAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'avatar_asset_id');
    }

    // ─── Scopes ─────────────────────────────────────────────────────

    /** الأعضاء النشِطون (المرئيّون للعامّة). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TeamMemberStatus::Active->value);
    }

    /** الترتيب اليدوي الحتمي: sort_order ثم id. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    // ─── SEO / sharing ──────────────────────────────────────────────

    /**
     * المسار القانوني المستقرّ: /team/{slug} (بلا locale prefix — نطاق عربيّ أحادي).
     * slug بشريّ مستقرّ — أنسب لروابط دائمة للـ SEO.
     */
    public function canonicalPath(): string
    {
        return '/'.trim("team/{$this->slug}", '/');
    }
}
