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
use App\Modules\Notifications\Support\RecipientBatch;
use App\Modules\Notifications\Support\RecipientResult;
use App\Modules\Notifications\Support\SendReport;
use App\Modules\Notifications\Support\ValidationResult;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * درايفر البريد — **يعيد استخدام بنية Mail القائمة** (mail.default = settings_smtp المُهيّأ من
 * AppServiceProvider). per_recipient فقط (لا topic). يستقبل RecipientBatch[عناوين] ويُرسل لكلّ
 * عنوان عبر Mail facade، ويُعيد SendReport. لا يرمي (يلتقط لكلّ رسالة).
 */
final class EmailDriver implements ChannelDriver
{
    public function key(): ChannelKey
    {
        return ChannelKey::Email;
    }

    public function addressing(): AddressingModel
    {
        return AddressingModel::PerRecipient;
    }

    public function capabilities(): ChannelCapabilities
    {
        return new ChannelCapabilities(bulk: true, media: false, html: true, deeplink: false, maxBatch: 100, topic: false);
    }

    public function enabled(): bool
    {
        return true; // لا flag تعطيل للبريد — التهيئة هي البوّابة
    }

    public function isConfigured(): bool
    {
        // mail.default الحقيقيّ (settings_smtp/smtp/ses…) لا log/array (يطابق تهيئة الموقع).
        return ! in_array((string) config('mail.default'), ['log', 'array'], true);
    }

    public function health(): ChannelHealth
    {
        // لا probe SMTP حيّ في v1 (مكلف) — التهيئة = صحّة؛ الفشل يظهر عبر تغذية الإرسال (degraded).
        return $this->isConfigured() ? ChannelHealth::healthy() : ChannelHealth::unconfigured();
    }

    public function validate(ChannelMessage $message): ValidationResult
    {
        return trim($message->title) === ''
            ? ValidationResult::invalid(['email: subject (title) required'])
            : ValidationResult::valid();
    }

    public function send(ChannelMessage $message, RecipientBatch $recipients, string $idempotencyKey): SendReport
    {
        if (! $this->isConfigured()) {
            return SendReport::skipped('email not configured');
        }

        $subject = $message->title !== '' ? $message->title : 'إشعار';
        $html = $this->html($message);
        $results = [];
        foreach ($recipients->recipients as $recipient) {
            try {
                Mail::html($html, function (Message $mail) use ($recipient, $subject): void {
                    $mail->to($recipient->address)->subject($subject);
                });
                $results[] = RecipientResult::sent($recipient->ref);
            } catch (Throwable $e) {
                $results[] = RecipientResult::failed($recipient->ref, $e->getMessage());
            }
        }

        return SendReport::forRecipients($results);
    }

    private function html(ChannelMessage $message): string
    {
        $body = nl2br(e($message->body));

        if ($message->deepLink !== null && $message->deepLink->type !== DeepLinkType::None && $message->deepLink->value !== null) {
            $url = e($message->deepLink->value);
            $body .= '<p><a href="'.$url.'">'.$url.'</a></p>';
        }

        return $body;
    }
}
