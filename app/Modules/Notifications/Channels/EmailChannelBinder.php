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
 * مُجسِّر البريد — يعيد استخدام `userQuery` للجمهور، يحلّ المستخدمين ذوي البريد → دفعات
 * RecipientBatch[عناوين] عبر lazyById (ذاكرة ثابتة). per_recipient فقط (لا topic).
 */
final class EmailChannelBinder implements ChannelBinder
{
    private const CHUNK = 100;

    public function __construct(private readonly AudienceResolverRegistry $audiences) {}

    public function channel(): ChannelKey
    {
        return ChannelKey::Email;
    }

    public function bind(AudienceResult $audience): iterable
    {
        $userQuery = $this->audiences->for($audience->type)->userQuery($audience);
        if ($userQuery === null) {
            return;
        }

        $userQuery = PreferenceFilter::excludeOptedOut($userQuery, 'users.id', ChannelKey::Email);

        $recipients = [];
        foreach ($userQuery->whereNotNull('email')->where('email', '!=', '')->select(['id', 'email'])->lazyById(self::CHUNK, 'id') as $user) {
            $recipients[] = new Recipient('user:'.$user->id, (string) $user->email);
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
