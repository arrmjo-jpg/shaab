<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdPlacementType;
use App\Enums\AdSelectorStrategy;
use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * مساحة إعلانية — موضع عرض مستقرّ يُعرّف بمفتاح ثابت (key) تستهلكه الواجهات عبر
 * GET /api/v1/ads/serve/{key}. الاختيار يتمّ في الخادم وفق selector_strategy.
 */
class AdZone extends Model
{
    use AuditsChanges;
    use HasFactory;

    /** اللغات المدعومة (مساحة قد تكون خاصّة بلغة أو null = الكل). */
    public const LOCALES = ['ar', 'en'];

    protected string $auditLogName = 'ad_zone';

    /** @var array<int,string> */
    protected array $auditAttributes = [
        'key', 'name', 'placement_type', 'selector_strategy', 'locale', 'is_active', 'sort_order',
    ];

    protected $fillable = [
        'key', 'name', 'description', 'placement_type', 'selector_strategy',
        'width', 'height', 'locale', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'placement_type' => AdPlacementType::class,
            'selector_strategy' => AdSelectorStrategy::class,
            'width' => 'integer',
            'height' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ─── Relationships ──────────────────────────────────────────────

    public function placements(): HasMany
    {
        return $this->hasMany(AdPlacement::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** مساحات لغة محدّدة + المساحات العامّة (locale = null). */
    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where(function (Builder $q) use ($locale): void {
            $q->whereNull('locale')->orWhere('locale', $locale);
        });
    }
}
