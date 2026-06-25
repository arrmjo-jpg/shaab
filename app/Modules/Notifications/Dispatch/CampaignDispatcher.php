<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Dispatch;

use App\Modules\Notifications\Audiences\AudienceResolverRegistry;
use App\Modules\Notifications\Channels\ChannelDriverRegistry;
use App\Modules\Notifications\Enums\AddressingModel;
use App\Modules\Notifications\Enums\AudienceType;
use App\Modules\Notifications\Enums\CampaignChannelStatus;
use App\Modules\Notifications\Enums\CampaignStatus;
use App\Modules\Notifications\Enums\ChannelKey;
use App\Modules\Notifications\Enums\DeliveryMode;
use App\Modules\Notifications\Enums\EventSource;
use App\Modules\Notifications\Enums\TrackingMode;
use App\Modules\Notifications\Enums\TriggerType;
use App\Modules\Notifications\Events\NotificationEvent;
use App\Modules\Notifications\Jobs\DispatchCampaignJob;
use App\Modules\Notifications\Models\NotificationCampaign;
use App\Modules\Notifications\Models\NotificationCampaignChannel;
use App\Modules\Notifications\Models\NotificationEventChannel;
use App\Modules\Notifications\Models\NotificationEventType;
use App\Modules\Notifications\Support\AudienceResult;
use App\Modules\Notifications\Support\CampaignDedupe;
use App\Modules\Notifications\Support\Decision;
use App\Modules\Notifications\Support\NotificationQueues;
use App\Modules\Notifications\Support\TemplateRenderer;
use App\Modules\Notifications\Support\TemplateResolver;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * موزّع الحملات — **السلطة الوحيدة** لإنشاء NotificationCampaign + NotificationCampaignChannel.
 * **ثابت الحجم** (سبيك جمهور فقط، صفر materialization/deliveries). يُصيّر القالب **مرّة واحدة عند
 * الإنشاء** ويُخزّن الناتج في snapshot القناة (لا re-render وقت الإرسال) ⇒ الحملة immutable حتى لو
 * تغيّر القالب لاحقًا. snapshot القناة كامل: mode/channel_priority/fallback_channel/template_id/
 * addressing/tracking_mode/content/topic — لا قراءة حيّة من المصفوفة أثناء الإرسال.
 */
final class CampaignDispatcher
{
    public function __construct(
        private readonly AudienceResolverRegistry $audiences,
        private readonly ChannelDriverRegistry $drivers,
        private readonly TemplateResolver $templates,
        private readonly TemplateRenderer $renderer,
    ) {}

    public function dispatch(NotificationEvent $event, Decision $decision): void
    {
        $plan = $this->channelPlan($event);
        if ($plan === []) {
            Log::info('notifications.campaign.no_channels', ['event' => $event->eventKey]);

            return;
        }

        $audience = $this->audienceSpec($event);
        $locale = $this->locale($event);
        $hash = CampaignDedupe::hash($event);
        $status = $this->initialStatus($event, $plan);

        [$campaign, $isNew] = $this->createAtomically($event, $decision, $audience, $plan, $hash, $status, $locale);
        if (! $isNew) {
            return; // مكرّر — dedupe_hash فاز به منشئ آخر
        }

        if ($campaign->status === CampaignStatus::Queued) {
            DispatchCampaignJob::dispatch($campaign->id)->onQueue(NotificationQueues::forPriority($decision->priority));
        }
    }

    /**
     * @param  array<int,array{channel:ChannelKey,mode:DeliveryMode,priority:int,fallback:?string,template_id:?int}>  $plan
     * @return array{0:NotificationCampaign,1:bool}
     */
    private function createAtomically(NotificationEvent $event, Decision $decision, AudienceResult $audience, array $plan, ?string $hash, CampaignStatus $status, string $locale): array
    {
        $build = fn (): NotificationCampaign => DB::transaction(function () use ($event, $decision, $audience, $plan, $hash, $status, $locale): NotificationCampaign {
            $campaign = NotificationCampaign::query()->create([
                'event_key' => $event->eventKey,
                'source' => $event->source->value,
                'trigger_type' => $this->triggerType($event->source)->value,
                'priority' => $decision->priority->value,
                'title' => $this->rawContent($event)['title'],
                'content' => $this->rawContent($event),
                'status' => $status->value,
                'audience_spec' => $audience->toArray(),
                'scheduled_at' => $this->scheduledAt($event),
                'dedupe_hash' => $hash,
                'created_by' => $event->payload['created_by'] ?? null,
            ]);

            foreach ($plan as $channel) {
                $this->createChannel($event, $campaign, $channel, $audience, $locale);
            }

            return $campaign;
        });

        if ($hash === null) {
            return [$build(), true];
        }

        try {
            return [$build(), true];
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return [NotificationCampaign::query()->where('dedupe_hash', $hash)->firstOrFail(), false];
            }

            throw $e;
        }
    }

    /** @param  array{channel:ChannelKey,mode:DeliveryMode,priority:int,fallback:?string,template_id:?int}  $channel */
    private function createChannel(NotificationEvent $event, NotificationCampaign $campaign, array $channel, AudienceResult $audience, string $locale): void
    {
        $key = $channel['channel'];

        if (! $this->drivers->has($key)) {
            $this->createChannelRow($campaign, $channel, CampaignChannelStatus::Skipped, AddressingModel::PerRecipient, TrackingMode::Aggregate, null, 'driver not available', [], null);

            return;
        }

        $driver = $this->drivers->for($key);
        if (! $driver->isConfigured()) {
            $this->createChannelRow($campaign, $channel, CampaignChannelStatus::Skipped, AddressingModel::PerRecipient, TrackingMode::Aggregate, null, 'channel not configured', [], null);

            return;
        }

        // addressing = قرار الحملة (capabilities تُعلِم فقط)؛ tracking_mode مشتقّ من addressing.
        $addressing = ($audience->hasTopic() && $driver->capabilities()->topic)
            ? AddressingModel::Topic
            : AddressingModel::PerRecipient;
        $trackingMode = $addressing === AddressingModel::Topic ? TrackingMode::Aggregate : TrackingMode::PerRecipient;
        $topic = $addressing === AddressingModel::Topic ? $audience->topic : null;

        // render مرّة واحدة ⇒ snapshot (immutable). القالب المربوط في المصفوفة له الأولويّة.
        [$content, $templateId] = $this->renderChannelContent($event, $campaign, $key, $locale, $channel['template_id'] ?? null);

        $this->createChannelRow($campaign, $channel, CampaignChannelStatus::Pending, $addressing, $trackingMode, $topic, null, $content, $templateId);
    }

    /**
     * القالب (event×channel×locale) مُصيَّر بمتغيّرات الحمولة، وإلّا المحتوى الخام (استبدال تدريجيّ).
     *
     * @return array{0:array<string,mixed>,1:?int}
     */
    private function renderChannelContent(NotificationEvent $event, NotificationCampaign $campaign, ChannelKey $channel, string $locale, ?int $pinnedTemplateId): array
    {
        // القالب المربوط في المصفوفة (template_id) له الأولويّة، وإلّا الحلّ بـlocale.
        $template = $this->templates->resolveById($pinnedTemplateId, $event->eventKey, $channel)
            ?? $this->templates->resolve($event->eventKey, $channel, $locale);
        if ($template === null) {
            return [is_array($campaign->content) ? $campaign->content : [], null]; // fallback: المحتوى الخام
        }

        $vars = $event->payload;
        $deepLinkValue = $this->renderer->render($template->deep_link_value, $vars);

        return [[
            'title' => $this->renderer->render($template->title, $vars),
            'body' => $this->renderer->render($template->body, $vars),
            'image_url' => $template->image_strategy === 'content' && is_scalar($vars['image_url'] ?? null) ? (string) $vars['image_url'] : null,
            'deep_link_type' => $template->deep_link_type ?? 'none',
            'deep_link_value' => $deepLinkValue !== '' ? $deepLinkValue : null,
        ], (int) $template->id];
    }

    /**
     * @param  array{channel:ChannelKey,mode:DeliveryMode,priority:int,fallback:?string,template_id:?int}  $channel
     * @param  array<string,mixed>  $content
     */
    private function createChannelRow(NotificationCampaign $campaign, array $channel, CampaignChannelStatus $status, AddressingModel $addressing, TrackingMode $trackingMode, ?string $topic, ?string $skipReason, array $content, ?int $templateId): void
    {
        NotificationCampaignChannel::query()->create([
            'campaign_id' => $campaign->id,
            'channel' => $channel['channel']->value,
            'mode' => $channel['mode']->value,
            'tracking_mode' => $trackingMode->value,
            'status' => $status->value,
            'skip_reason' => $skipReason,
            'addressing' => $addressing->value,
            'channel_priority' => $channel['priority'],
            'fallback_channel' => $channel['fallback'],
            'template_id' => $templateId,
            'content' => $content,
            'topic' => $topic,
        ]);
    }

    /**
     * @return array<int,array{channel:ChannelKey,mode:DeliveryMode,priority:int,fallback:?string,template_id:?int}>
     */
    private function channelPlan(NotificationEvent $event): array
    {
        $override = $event->payload['channels'] ?? null;
        if (is_array($override) && $override !== []) {
            $plan = [];
            $priority = 1;
            foreach ($override as $value) {
                $key = ChannelKey::tryFrom((string) $value);
                if ($key !== null) {
                    $plan[] = ['channel' => $key, 'mode' => DeliveryMode::Automatic, 'priority' => $priority++, 'fallback' => null, 'template_id' => null];
                }
            }

            return $plan;
        }

        $eventRow = NotificationEventType::query()->where('key', $event->eventKey)->first();
        if ($eventRow === null) {
            return [];
        }

        return NotificationEventChannel::query()
            ->where('event_id', $eventRow->id)
            ->where('mode', '!=', DeliveryMode::Disabled->value)
            ->orderBy('channel_priority')
            ->get()
            ->map(fn (NotificationEventChannel $row): array => [
                'channel' => $row->channel,
                'mode' => $row->mode,
                'priority' => (int) $row->channel_priority,
                'fallback' => $row->fallback_channel,
                'template_id' => $row->template_id !== null ? (int) $row->template_id : null,
            ])
            ->all();
    }

    private function audienceSpec(NotificationEvent $event): AudienceResult
    {
        $type = isset($event->payload['audience'])
            ? (AudienceType::tryFrom((string) $event->payload['audience']) ?? AudienceType::All)
            : AudienceType::All;
        if (! $this->audiences->has($type)) {
            $type = AudienceType::All;
        }
        $params = $event->payload['audience_params'] ?? [];

        return $this->audiences->for($type)->describe(is_array($params) ? $params : []);
    }

    /** @param  array<int,array{mode:DeliveryMode}>  $plan */
    private function initialStatus(NotificationEvent $event, array $plan): CampaignStatus
    {
        if (! empty($event->payload['requires_approval'])) {
            return CampaignStatus::Draft; // تأليف يدويّ يطلب موافقة صريحة
        }

        foreach ($plan as $channel) {
            if ($channel['mode'] === DeliveryMode::ManualApproval) {
                return CampaignStatus::Draft;
            }
        }

        $scheduledAt = $this->scheduledAt($event);

        return $scheduledAt !== null && $scheduledAt->isFuture() ? CampaignStatus::Scheduled : CampaignStatus::Queued;
    }

    private function scheduledAt(NotificationEvent $event): ?Carbon
    {
        $value = $event->payload['scheduled_at'] ?? null;

        return $value !== null ? Carbon::parse((string) $value) : null;
    }

    private function locale(NotificationEvent $event): string
    {
        return (string) ($event->payload['locale'] ?? config('app.locale', 'ar'));
    }

    /** @return array{title:?string,body:?string,image_url:?string,deep_link_type:string,deep_link_value:?string} */
    private function rawContent(NotificationEvent $event): array
    {
        $p = $event->payload;

        return [
            'title' => isset($p['title']) ? (string) $p['title'] : null,
            'body' => isset($p['body']) ? (string) $p['body'] : null,
            'image_url' => isset($p['image_url']) ? (string) $p['image_url'] : null,
            'deep_link_type' => isset($p['deep_link_type']) ? (string) $p['deep_link_type'] : 'none',
            'deep_link_value' => isset($p['deep_link_value']) ? (string) $p['deep_link_value'] : null,
        ];
    }

    private function triggerType(EventSource $source): TriggerType
    {
        return match ($source) {
            EventSource::Manual => TriggerType::Manual,
            EventSource::Scheduled => TriggerType::Scheduled,
            default => TriggerType::Automatic,
        };
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        // النوع المخصّص الذي يرميه Laravel لكلّ سائق (mysql/sqlite/…) عند خرق UNIQUE فقط —
        // أدقّ وأمتن من فحص الرسالة/الرقم، ولا يشمل FK/NOT-NULL (التي تبقى QueryException عامّة).
        return $e instanceof UniqueConstraintViolationException;
    }
}
