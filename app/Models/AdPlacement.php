<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إسناد إبداع ↔ مساحة — المرشّح القابل للعرض. وزن الإسناد (إن وُجد) يتجاوز وزن
 * الإبداع. تُبنى منه بِركة المرشّحين لكل مساحة (Batch 2).
 */
class AdPlacement extends Model
{
    use AuditsChanges;
    use HasFactory;

    protected string $auditLogName = 'ad_placement';

    /** @var array<int,string> */
    protected array $auditAttributes = [
        'ad_creative_id', 'ad_zone_id', 'weight', 'is_active', 'device_targets',
    ];

    protected $fillable = [
        'ad_creative_id', 'ad_zone_id', 'weight', 'is_active', 'device_targets',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'is_active' => 'boolean',
            'device_targets' => 'array',
        ];
    }

    // ─── Relationships ──────────────────────────────────────────────

    public function creative(): BelongsTo
    {
        return $this->belongsTo(AdCreative::class, 'ad_creative_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(AdZone::class, 'ad_zone_id');
    }

    // ─── Scopes ─────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** الوزن الفعّال: وزن الإسناد إن وُجد، وإلا وزن الإبداع، وإلا 1. */
    public function effectiveWeight(): int
    {
        return $this->weight ?? $this->creative?->weight ?? 1;
    }

    /** أهليّة الجهاز: فارغ/null = كل الأجهزة؛ وإلا يجب أن يكون الجهاز ضمن القائمة. */
    public function eligibleForDevice(string $device): bool
    {
        $targets = $this->device_targets;

        return empty($targets) || in_array($device, (array) $targets, true);
    }
}
