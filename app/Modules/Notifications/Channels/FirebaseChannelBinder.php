<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

use App\Modules\Notifications\Audiences\AudienceResolverRegistry;
use App\Modules\Notifications\Contracts\ChannelBinder;
use App\Modules\Notifications\Enums\ChannelKey;
use App\Modules\Notifications\Models\MobileDevice;
use App\Modules\Notifications\Support\AudienceResult;
use App\Modules\Notifications\Support\PreferenceFilter;
use App\Modules\Notifications\Support\Recipient;
use App\Modules\Notifications\Support\RecipientBatch;
use Illuminate\Database\Eloquent\Builder;

/**
 * مُجسِّر Firebase — يحوّل AudienceResult إلى RecipientBatch:
 *   topic     ⇒ نشرة topic واحدة (O(1)).
 *   غير topic ⇒ deviceQuery (مباشر أو user→devices عبر subquery) → lazyById(500) → دفعات tokens.
 * **Generator** بذاكرة ثابتة (500 في كلّ لحظة، لا materialization) — يطابق النمط الإلزاميّ
 * Campaign → AudienceResult → Resolver → Query → chunk → RecipientBatch → Driver.
 */
final class FirebaseChannelBinder implements ChannelBinder
{
    private const CHUNK = 500;

    public function __construct(private readonly AudienceResolverRegistry $resolvers) {}

    public function channel(): ChannelKey
    {
        return ChannelKey::Firebase;
    }

    public function bind(AudienceResult $audience): iterable
    {
        if ($audience->hasTopic()) {
            yield RecipientBatch::forTopic((string) $audience->topic);

            return;
        }

        $deviceQuery = $this->deviceQueryFor($audience);
        if ($deviceQuery === null) {
            return;
        }

        // استبعاد مَن ألغى اشتراك push (كتم عامّ) — opt-out يُحترَم على الحملات لا الـtopics وحدها.
        $deviceQuery = PreferenceFilter::excludeOptedOut($deviceQuery, 'mobile_devices.user_id', ChannelKey::Firebase);

        $recipients = [];
        foreach ($deviceQuery->select(['id', 'fcm_token', 'locale'])->lazyById(self::CHUNK, 'id') as $device) {
            $recipients[] = new Recipient('device:'.$device->id, (string) $device->fcm_token, $device->locale);
            if (count($recipients) >= self::CHUNK) {
                yield RecipientBatch::forRecipients($recipients);
                $recipients = [];
            }
        }

        if ($recipients !== []) {
            yield RecipientBatch::forRecipients($recipients);
        }
    }

    /**
     * استعلام الأجهزة الحيّ من السبيك: deviceQuery مباشرة، وإلّا user→devices عبر subquery
     * (لا materialization). null = الجمهور غير قابل للـpush.
     *
     * @return Builder<MobileDevice>|null
     */
    private function deviceQueryFor(AudienceResult $audience): ?Builder
    {
        $resolver = $this->resolvers->for($audience->type);

        $deviceQuery = $resolver->deviceQuery($audience);
        if ($deviceQuery !== null) {
            return $deviceQuery;
        }

        $userQuery = $resolver->userQuery($audience);
        if ($userQuery === null) {
            return null;
        }

        return MobileDevice::query()
            ->where('is_active', true)
            ->whereNotNull('fcm_token')
            ->whereIn('user_id', $userQuery->select('id'));
    }
}
