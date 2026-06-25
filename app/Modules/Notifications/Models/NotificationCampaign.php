<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Models\User;
use App\Modules\Notifications\Enums\CampaignStatus;
use App\Modules\Notifications\Enums\EventSource;
use App\Modules\Notifications\Enums\Priority;
use App\Modules\Notifications\Enums\TriggerType;
use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * حملة إشعار — وحدة التنسيق. تُنشَأ حصرًا في CampaignDispatcher (السلطة الوحيدة). dedupe_hash
 * فريد (منع التكرار الذرّيّ). audience_spec = AudienceResult المُسلسَل (سبيك، لا مستلمون). الحالة
 * الإجماليّة مشتقّة من حالات القنوات. مُدقَّقة (حجم معقول، قرارات ذات معنى).
 */
class NotificationCampaign extends Model
{
    use AuditsChanges;

    protected $table = 'notification_campaigns';

    protected string $auditLogName = 'notification_campaign';

    /** @var array<int,string> */
    protected array $auditAttributes = ['uuid', 'event_key', 'status', 'priority', 'scheduled_at'];

    protected $fillable = [
        'uuid', 'event_key', 'source', 'trigger_type', 'priority', 'title', 'status',
        'audience_id', 'audience_spec', 'scheduled_at', 'started_at', 'finished_at',
        'targeted_count', 'dedupe_hash', 'created_by', 'content',
    ];

    protected function casts(): array
    {
        return [
            'source' => EventSource::class,
            'trigger_type' => TriggerType::class,
            'priority' => Priority::class,
            'status' => CampaignStatus::class,
            'audience_spec' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'targeted_count' => 'integer',
            'content' => 'array',
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

    public function channels(): HasMany
    {
        return $this->hasMany(NotificationCampaignChannel::class, 'campaign_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid'; // مسارات الإدارة تربط بالـuuid لا الـid
    }
}
