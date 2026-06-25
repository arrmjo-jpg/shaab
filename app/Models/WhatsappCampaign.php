<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WhatsappCampaignStatus;
use App\Enums\WhatsappCampaignType;
use App\Enums\WhatsappMediaType;
use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * حملة واتساب — نوعان فقط: إعلانية (نص/صورة/فيديو/صورة+نص/فيديو+نص) أو خبر (يُجلب
 * عنوانه/صورته/ملخصه/رابطه تلقائياً من المقال). المستلمون = مجموعات محددة فقط.
 * العدادات (recipients/sent/failed) تُحدَّث ذرّياً من Jobs الإرسال.
 */
class WhatsappCampaign extends Model
{
    use AuditsChanges;
    use SoftDeletes;

    protected string $auditLogName = 'whatsapp_campaign';

    /** @var array<int,string> */
    protected array $auditAttributes = [
        'name', 'type', 'status', 'media_type', 'article_id', 'scheduled_at',
    ];

    protected $fillable = [
        'uuid', 'name', 'type', 'status', 'message_text', 'media_type',
        'media_asset_id', 'article_id', 'scheduled_at', 'started_at', 'finished_at',
        'recipients_total', 'sent_count', 'failed_count', 'dedupe_hash', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => WhatsappCampaignType::class,
            'status' => WhatsappCampaignStatus::class,
            'media_type' => WhatsappMediaType::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'recipients_total' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
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

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(WhatsappGroup::class, 'whatsapp_campaign_group');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappCampaignMessage::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
