<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

use App\Modules\Notifications\Audiences\AudienceResolverRegistry;
use App\Modules\Notifications\Contracts\ChannelBinder;
use App\Modules\Notifications\Enums\ChannelKey;
use App\Modules\Notifications\Support\AudienceResult;
use App\Modules\Notifications\Support\PreferenceFilter;
use App\Modules\Notifications\Support\Recipient;
use App\Modules\Notifications\Support\RecipientBatch;

/**
 * مُجسِّر WhatsApp — يعيد استخدام `userQuery` للجمهور (لا منطق recipients جديد)، يحلّ المستخدمين
 * ذوي الهاتف → دفعات RecipientBatch[هواتف] عبر lazyById (ذاكرة ثابتة). per_recipient فقط (لا topic).
 */
final class WhatsAppChannelBinder implements ChannelBinder
{
    private const CHUNK = 50;

    public function __construct(private readonly AudienceResolverRegistry $audiences) {}

    public function channel(): ChannelKey
    {
        return ChannelKey::Whatsapp;
    }

    public function bind(AudienceResult $audience): iterable
    {
        $userQuery = $this->audiences->for($audience->type)->userQuery($audience);
        if ($userQuery === null) {
            return; // جمهور غير قابل للحلّ لمستخدمين (مثل cohort أجهزة/topic)
        }

        $userQuery = PreferenceFilter::excludeOptedOut($userQuery, 'users.id', ChannelKey::Whatsapp);

        $recipients = [];
        foreach ($userQuery->whereNotNull('phone')->where('phone', '!=', '')->select(['id', 'phone'])->lazyById(self::CHUNK, 'id') as $user) {
            $recipients[] = new Recipient('user:'.$user->id, (string) $user->phone);
            if (count($recipients) >= self::CHUNK) {
                yield RecipientBatch::forRecipients($recipients);
                $recipients = [];
            }
        }

        if ($recipients !== []) {
            yield RecipientBatch::forRecipients($recipients);
        }
    }
}
