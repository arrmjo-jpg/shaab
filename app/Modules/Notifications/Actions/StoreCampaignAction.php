<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Enums\EventSource;
use App\Modules\Notifications\Events\NotificationEvent;
use App\Modules\Notifications\Models\NotificationCampaign;
use App\Modules\Notifications\NotificationManager;
use App\Modules\Notifications\Support\CampaignDedupe;
use App\Modules\Notifications\Support\CampaignTransitionException;
use Illuminate\Support\Str;

/**
 * تأليف حملة يدويّة — يمرّ عبر **NotificationManager (المدخل الوحيد)**: يبني NotificationEvent
 * (source=manual، الحمولة تحمل العنوان/المتغيّرات/الجمهور/القنوات) ويسلّمه. لا ينشئ حملة مباشرة
 * (الإنشاء حصرٌ على CampaignDispatcher). idempotency_key يمنع النقر المزدوج (dedupe_hash).
 * يُعيد الحملة المُنشأة عبر dedupe_hash؛ غيابها ⇒ الحدث تُجوهِل (معطّل) ⇒ 422.
 */
final class StoreCampaignAction
{
    public function __construct(private readonly NotificationManager $manager) {}

    /**
     * @param  array<string,mixed>  $data
     */
    public function handle(array $data, ?int $userId): NotificationCampaign
    {
        $idempotencyKey = (string) ($data['idempotency_key'] ?? Str::uuid());

        // الحمولة = المتغيّرات (لاستبدال القالب) + حقول المحتوى/التوجيه العلويّة.
        $payload = array_merge(
            is_array($data['variables'] ?? null) ? $data['variables'] : [],
            array_filter([
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
                'image_url' => $data['image_url'] ?? null,
                'deep_link_type' => $data['deep_link_type'] ?? null,
                'deep_link_value' => $data['deep_link_value'] ?? null,
                'audience' => $data['audience'] ?? null,
                'audience_params' => $data['audience_params'] ?? null,
                'channels' => $data['channels'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'requires_approval' => $data['requires_approval'] ?? null,
                'locale' => $data['locale'] ?? null,
            ], static fn ($v): bool => $v !== null),
            ['idempotency_key' => $idempotencyKey, 'created_by' => $userId],
        );

        $event = new NotificationEvent((string) $data['event_key'], EventSource::Manual, $payload);

        $this->manager->handle($event);

        $hash = CampaignDedupe::hash($event);
        $campaign = NotificationCampaign::query()->where('dedupe_hash', $hash)->first();

        if ($campaign === null) {
            throw new CampaignTransitionException('تعذّر إنشاء الحملة — الحدث متجاهَل (قد يكون معطّلاً في المصفوفة).');
        }

        return $campaign;
    }
}
