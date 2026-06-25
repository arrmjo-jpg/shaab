<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

use App\Modules\Notifications\Contracts\ChannelDriver;
use App\Modules\Notifications\Enums\AddressingModel;
use App\Modules\Notifications\Enums\ChannelKey;
use App\Modules\Notifications\Enums\DeepLinkType;
use App\Modules\Notifications\Support\ChannelCapabilities;
use App\Modules\Notifications\Support\ChannelHealth;
use App\Modules\Notifications\Support\ChannelMessage;
use App\Modules\Notifications\Support\Recipient;
use App\Modules\Notifications\Support\RecipientBatch;
use App\Modules\Notifications\Support\RecipientResult;
use App\Modules\Notifications\Support\SendReport;
use App\Modules\Notifications\Support\ValidationResult;
use App\Settings\ThirdPartySettings;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

/**
 * درايفر Firebase Push (FCM) — ناقل أصمّ: لا يعرف Events/Campaigns/Audiences/Preferences. يبني
 * Messaging من الـService Account المخزّن (ThirdPartySettings)، ويُرسل ما يصله في RecipientBatch:
 *   topic         ⇒ نشرة واحدة (send) — مسار المليون (O(1)).
 *   per_recipient ⇒ multicast للتوكنات (≤500/نداء؛ التقسيم مسؤوليّة المُنسّق).
 * يُعيد SendReport ولا يرمي للطبقات العليا. التوكنات الميتة (UNREGISTERED/INVALID) تُبلَّغ عبر
 * invalidRefs ⇒ طبقة التنسيق تُعطّلها (الدرايفر لا يعرف mobile_devices).
 */
final class FirebaseDriver implements ChannelDriver
{
    private ?Messaging $messaging = null;

    public function key(): ChannelKey
    {
        return ChannelKey::Firebase;
    }

    public function addressing(): AddressingModel
    {
        // افتراضيّ per_recipient؛ يدعم topic أيضاً (capabilities()->topic) — send() يفرّع على mode الدفعة.
        return AddressingModel::PerRecipient;
    }

    public function capabilities(): ChannelCapabilities
    {
        return new ChannelCapabilities(
            bulk: true,
            media: true,
            html: false,
            deeplink: true,
            maxBatch: 500,
            topic: true,
        );
    }

    public function enabled(): bool
    {
        return $this->settings()->firebase_enabled;
    }

    public function isConfigured(): bool
    {
        $s = $this->settings();

        return $s->firebase_enabled && $s->firebase_service_account_json !== '';
    }

    public function health(): ChannelHealth
    {
        // configured = وجود الاعتماد فقط (مستقلّ عن enabled) — السجلّ يدمج enabled لحساب effective_state.
        if ($this->settings()->firebase_service_account_json === '') {
            return ChannelHealth::unconfigured();
        }

        try {
            $start = microtime(true);
            // probe حيّ: validate (validate_only) يؤكّد الاعتماد + صلاحيّة الرسالة دون إرسال فعليّ.
            $this->messaging()->validate(
                CloudMessage::new()->withTopic('healthcheck')->withNotification(Notification::create('health', 'check')),
            );

            return ChannelHealth::healthy((int) ((microtime(true) - $start) * 1000));
        } catch (Throwable $e) {
            return ChannelHealth::problem($e->getMessage());
        }
    }

    public function validate(ChannelMessage $message): ValidationResult
    {
        if (trim($message->title) === '' && trim($message->body) === '') {
            return ValidationResult::invalid(['firebase: notification requires a title or body']);
        }

        return ValidationResult::valid();
    }

    public function send(ChannelMessage $message, RecipientBatch $recipients, string $idempotencyKey): SendReport
    {
        if (! $this->isConfigured()) {
            return SendReport::skipped('firebase not configured');
        }

        try {
            $cloud = $this->buildMessage($message);

            return $recipients->mode === AddressingModel::Topic
                ? $this->sendToTopic($cloud, (string) $recipients->topic)
                : $this->sendToTokens($cloud, $recipients);
        } catch (Throwable $e) {
            // فشل كلّيّ (اعتماد/شبكة/رسالة غير صالحة) — فشل الدفعة لا تخطٍّ (القناة مهيّأة).
            return $recipients->mode === AddressingModel::Topic
                ? SendReport::forTopic(false, null, $e->getMessage())
                : $this->allFailed($recipients, $e->getMessage());
        }
    }

    private function sendToTopic(CloudMessage $message, string $topic): SendReport
    {
        $result = $this->messaging()->send($message->withTopic($topic));

        return SendReport::forTopic(true, $this->messageId($result));
    }

    private function sendToTokens(CloudMessage $message, RecipientBatch $recipients): SendReport
    {
        $tokenToRef = [];
        $tokens = [];
        foreach ($recipients->recipients as $recipient) {
            $tokenToRef[$recipient->address] = $recipient->ref;
            $tokens[] = $recipient->address;
        }

        $report = $this->messaging()->sendMulticast($message, $tokens);

        $results = [];
        foreach ($report->getItems() as $item) {
            $token = (string) $item->target()->value();
            $ref = $tokenToRef[$token] ?? $token;

            if ($item->isSuccess()) {
                $results[] = RecipientResult::sent($ref, $this->messageId($item->result()));
            } elseif ($item->messageTargetWasInvalid() || $item->messageWasSentToUnknownToken()) {
                $results[] = RecipientResult::invalid($ref, (string) $item->error()?->getMessage());
            } else {
                $results[] = RecipientResult::failed($ref, (string) $item->error()?->getMessage());
            }
        }

        return SendReport::forRecipients($results);
    }

    private function allFailed(RecipientBatch $recipients, string $error): SendReport
    {
        return SendReport::forRecipients(array_map(
            static fn (Recipient $r): RecipientResult => RecipientResult::failed($r->ref, $error),
            $recipients->recipients,
        ));
    }

    private function buildMessage(ChannelMessage $message): CloudMessage
    {
        $data = $this->stringData($message->data);

        if ($message->deepLink !== null && $message->deepLink->type !== DeepLinkType::None) {
            $data['deeplink_type'] = $message->deepLink->type->value;
            $data['deeplink_value'] = (string) ($message->deepLink->value ?? '');
        }

        return CloudMessage::new()
            ->withNotification(Notification::create($message->title, $message->body, $message->imageUrl))
            ->withData($data)
            ->withDefaultSounds();
    }

    /**
     * حمولة FCM data يجب أن تكون نصّ⇒نصّ.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,string>
     */
    private function stringData(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $out[(string) $key] = is_scalar($value) ? (string) $value : (string) json_encode($value);
        }

        return $out;
    }

    /** @param  array<array-key,scalar>|null  $result */
    private function messageId(?array $result): ?string
    {
        return isset($result['name']) ? (string) $result['name'] : null;
    }

    private function messaging(): Messaging
    {
        if ($this->messaging === null) {
            /** @var array<string,mixed> $credentials */
            $credentials = (array) json_decode($this->settings()->firebase_service_account_json, true);
            $this->messaging = (new Factory)->withServiceAccount($credentials)->createMessaging();
        }

        return $this->messaging;
    }

    private function settings(): ThirdPartySettings
    {
        return app(ThirdPartySettings::class);
    }
}
