<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\DeepLinkType;

/** رابط عميق مُهيكَل يُرسَل في حمولة data — يقرؤه التطبيق فيوجّه داخليًّا. */
final class DeepLink
{
    public function __construct(
        public readonly DeepLinkType $type,
        public readonly ?string $value = null,
    ) {}

    public static function none(): self
    {
        return new self(DeepLinkType::None);
    }

    public static function to(DeepLinkType $type, ?string $value): self
    {
        return new self($type, $value);
    }

    /** @return array{type:string,value:?string} */
    public function toArray(): array
    {
        return ['type' => $this->type->value, 'value' => $this->value];
    }
}
