<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdCampaignStatus;
use App\Enums\AdCreativeType;
use App\Enums\AdPacingMode;
use App\Support\Advertising\CampaignPublishValidation;
use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * حملة إعلانية — حاوية الجدولة/الأولوية/الوزن. تملك إبداعات. حقول الميزانية/الوتيرة/
 * الاستهداف جاهزة-مستقبلاً (لا محرّك الآن). الأهليّة للعرض = حالة مؤهَّلة (scheduled أو active)
 * + ضمن النافذة الزمنية — **التواريخ مصدر الحقيقة**، لا تعتمد على ترقية المجدول.
 */
class AdCampaign extends Model
{
    use AuditsChanges;
    use HasFactory;
    use SoftDeletes;

    protected string $auditLogName = 'ad_campaign';

    /** @var array<int,string> */
    protected array $auditAttributes = [
        'name', 'advertiser_name', 'status', 'priority', 'weight',
        'starts_at', 'ends_at', 'budget_total', 'pacing_mode',
    ];

    protected $fillable = [
        'uuid', 'name', 'advertiser_name', 'status', 'priority', 'weight',
        'starts_at', 'ends_at', 'budget_total', 'budget_spent', 'pacing_mode',
        'targeting', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AdCampaignStatus::class,
            'pacing_mode' => AdPacingMode::class,
            'priority' => 'integer',
            'weight' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'budget_total' => 'decimal:2',
            'budget_spent' => 'decimal:2',
            'targeting' => 'array',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $campaign): void {
            if (empty($campaign->uuid)) {
                $campaign->uuid = (string) Str::uuid();
            }
        });
    }

    // ─── Relationships ──────────────────────────────────────────────

    public function creatives(): HasMany
    {
        return $this->hasMany(AdCreative::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ─── Scopes ─────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AdCampaignStatus::Active->value);
    }

    /** ضمن النافذة الزمنية: بدأت (أو بلا بداية) ولم تنتهِ (أو بلا نهاية). */
    public function scopeInFlight(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    /**
     * مرشّحة فعلاً للعرض: حالة مؤهَّلة (scheduled أو active) + ضمن النافذة الزمنية. التواريخ
     * مصدر الحقيقة — لا تعتمد على ترقية المجدول؛ حملة scheduled ضمن نافذتها تُخدَم فورًا.
     */
    public function scopeServable(Builder $query): Builder
    {
        return $query->whereIn('status', AdCampaignStatus::servableValues())->inFlight();
    }

    /**
     * أهليّة لحظية للعرض (مرآة scopeServable للمثيل) — تُستخدم لإعادة التحقّق وقت العرض
     * (V3): النافذة الزمنية تُقيَّم على الساعة الحاليّة لا على لحظة بناء البِركة المُكاشة.
     */
    public function isServable(): bool
    {
        if (! $this->status->isServable()) {
            return false;
        }

        $now = now();

        return ($this->starts_at === null || $this->starts_at->lessThanOrEqualTo($now))
            && ($this->ends_at === null || $this->ends_at->greaterThanOrEqualTo($now));
    }

    /**
     * فحص قابليّة النشر — **مصدر الحقيقة الوحيد** (Result Object). يُستهلَك من أيّ شاشة/API/زرّ؛
     * يُمنع إعادة كتابة شروط النشر في أيّ مكان آخر. التوسعة المستقبليّة (ميزانية/موافقة/صلاحية
     * مُعلِن) تُضاف هنا فقط. الترتيب: الأرخص (بلا استعلام) أوّلاً ثمّ الاستعلامات الأغلى.
     */
    public function publishValidation(): CampaignPublishValidation
    {
        // (1) وجود تاريخ بداية — بلا استعلام.
        if ($this->starts_at === null) {
            return CampaignPublishValidation::fail('missing_start_date', 'ads.campaign.no_start_date');
        }

        // (2) صحّة التواريخ (ends_at ≥ starts_at إن وُجد) — بلا استعلام.
        if ($this->ends_at !== null && $this->ends_at->lessThan($this->starts_at)) {
            return CampaignPublishValidation::fail('invalid_dates', 'ads.campaign.bad_dates');
        }

        // (3) إبداع نشط واحد على الأقل (أرخص استعلام).
        if (! $this->creatives()->where('is_active', true)->exists()) {
            return CampaignPublishValidation::fail('no_creative', 'ads.campaign.no_creative');
        }

        // إبداع نشط قابل للعرض حسب نوعه (صورة بوسيط، أو HTML بمحتوى؛ فيديو غير مدعوم) — استعلام مُعاد البناء.
        $renderable = fn () => $this->creatives()
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->where(fn ($img) => $img->where('type', AdCreativeType::Image->value)->whereNotNull('media_asset_id'))
                    ->orWhere(fn ($html) => $html->where('type', AdCreativeType::Html->value)
                        ->whereNotNull('html_code')->where('html_code', '!=', ''));
            });

        // (4) أن يكون الإبداع قابلًا للعرض.
        if (! $renderable()->exists()) {
            return CampaignPublishValidation::fail('creative_not_renderable', 'ads.campaign.creative_not_renderable');
        }

        // (5) إسناد نشط على إبداع قابل للعرض.
        if (! $renderable()->whereHas('placements', fn ($p) => $p->where('is_active', true))->exists()) {
            return CampaignPublishValidation::fail('no_active_placement', 'ads.campaign.no_placement');
        }

        // (6) الإسناد مربوط بمساحة (Zone) نشطة.
        $onActiveZone = $renderable()->whereHas('placements', fn ($p) => $p->where('is_active', true)
            ->whereHas('zone', fn ($z) => $z->where('is_active', true)))->exists();
        if (! $onActiveZone) {
            $zoneId = AdPlacement::query()
                ->where('is_active', true)
                ->whereHas('creative', fn ($c) => $c->where('ad_campaign_id', $this->getKey())->where('is_active', true))
                ->whereHas('zone', fn ($z) => $z->where('is_active', false))
                ->value('ad_zone_id');

            return CampaignPublishValidation::fail('zone_inactive', 'ads.campaign.zone_inactive',
                $zoneId !== null ? ['zone_id' => (int) $zoneId] : []);
        }

        // نقطة التوسعة الوحيدة لشروط النشر — أيّ شرط جديد يُضاف هنا فقط (لا في API/الواجهة/الانتقال).
        // أسباب محجوزة مستقبليّة: budget_exhausted (تجاوز budget_total)، advertiser_disabled (تعطيل المُعلِن).
        return CampaignPublishValidation::pass();
    }

    /**
     * قابلة للنشر — **Convenience فقط** (مشتقّة من publishValidation، بلا منطق مستقل). لا يعتمد
     * عليها النظام للقرار؛ الـAPI/الإدارة/الأزرار/CLI تستدعي publishValidation() وتقرأ ok/reason/
     * messageKey/details. تبقى للاستخدامات التي تحتاج yes/no فقط.
     */
    public function isPublishable(): bool
    {
        return $this->publishValidation()->ok;
    }
}
