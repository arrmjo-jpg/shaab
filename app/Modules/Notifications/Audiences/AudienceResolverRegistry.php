<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Audiences;

use App\Modules\Notifications\Contracts\AudienceResolver;
use App\Modules\Notifications\Enums\AudienceType;
use InvalidArgumentException;

/**
 * سجلّ مُحلِّلات الجمهور — AudienceType → resolver. يحلّ الـResolver وقت التنفيذ من نوع السبيك
 * (لا يُسلسَل الـResolver؛ يُحلّ من الحاوية في الـjob). v1.1: الثمانية المعتمدة فقط.
 */
final class AudienceResolverRegistry
{
    /** @var array<string,AudienceResolver> */
    private array $resolvers = [];

    public function __construct()
    {
        foreach ([
            new AllUsersResolver,
            new LoggedResolver,
            new GuestsResolver,
            new AndroidResolver,
            new IosResolver,
            new SportsFollowersResolver,
            new WhatsappSubscribersResolver,
            new EmailSubscribersResolver,
        ] as $resolver) {
            $this->resolvers[$resolver->type()->value] = $resolver;
        }
    }

    public function for(AudienceType $type): AudienceResolver
    {
        return $this->resolvers[$type->value]
            ?? throw new InvalidArgumentException("no audience resolver for: {$type->value}");
    }

    public function has(AudienceType $type): bool
    {
        return isset($this->resolvers[$type->value]);
    }
}
