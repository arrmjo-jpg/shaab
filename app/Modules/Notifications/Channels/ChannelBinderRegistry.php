<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

use App\Modules\Notifications\Audiences\AudienceResolverRegistry;
use App\Modules\Notifications\Contracts\ChannelBinder;
use App\Modules\Notifications\Enums\ChannelKey;

/**
 * سجلّ مُجسِّرات القنوات — ChannelKey → ChannelBinder. Firebase + WhatsApp + Email.
 * يستهلكه DispatchCampaignJob لتحويل AudienceResult → دفعات RecipientBatch لكلّ قناة.
 */
final class ChannelBinderRegistry
{
    /** @var array<string,ChannelBinder> */
    private array $binders = [];

    public function __construct(AudienceResolverRegistry $audiences)
    {
        foreach ([new FirebaseChannelBinder($audiences), new WhatsAppChannelBinder($audiences), new EmailChannelBinder($audiences)] as $binder) {
            $this->binders[$binder->channel()->value] = $binder;
        }
    }

    public function for(ChannelKey $key): ?ChannelBinder
    {
        return $this->binders[$key->value] ?? null;
    }

    public function has(ChannelKey $key): bool
    {
        return isset($this->binders[$key->value]);
    }
}
