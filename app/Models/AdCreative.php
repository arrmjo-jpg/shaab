<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdCreativeType;
use App\Support\Advertising\AdHtmlSanitizer;
use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * إبداع إعلاني — الوحدة المعروضة. صورة/فيديو عبر أصل مركزيّ (media_asset_id) أو
 * كود HTML مُنقّى (html_code). يتبع حملة. الوزن صريح للتدوير (ليس بالنقرات).
 */
class AdCreative extends Model
{
    use AuditsChanges;
    use HasFactory;
    use SoftDeletes;

    protected string $auditLogName = 'ad_creative';

    /** @var array<int,string> ملاحظة: html_code مستثنى (حجم/تنقية) — يُدقَّق التحوّل فقط. */
    protected array $auditAttributes = [
        'ad_campaign_id', 'type', 'title', 'landing_url', 'media_asset_id', 'weight', 'is_active',
    ];

    protected $fillable = [
        'uuid', 'ad_campaign_id', 'type', 'title', 'alt_text', 'landing_url',
        'html_code', 'media_asset_id', 'weight', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => AdCreativeType::class,
            'weight' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * تنقية html_code عند الكتابة — حدّ دفاع في النموذج (V8): أيّ مسار كتابة (Action،
     * بذرة، مستورد، عملية جماعية) يُنقَّى تلقائياً عبر HTMLPurifier، لا يعتمد على تذكّر
     * كلّ مُستدعٍ. null يمرّ كما هو للحفاظ على نظافة الحقول المتقاطعة (إبداع صورة → null).
     */
    protected function htmlCode(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value === null ? null : AdHtmlSanitizer::sanitize($value),
        );
    }

    protected static function booted(): void
    {
        static::creating(function (self $creative): void {
            if (empty($creative->uuid)) {
                $creative->uuid = (string) Str::uuid();
            }
        });
    }

    // ─── Relationships ──────────────────────────────────────────────

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public function placements(): HasMany
    {
        return $this->hasMany(AdPlacement::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
