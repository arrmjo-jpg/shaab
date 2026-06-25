<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\DispatchMode;
use App\Modules\Notifications\Enums\Priority;

/**
 * قرار التوجيه الذي يُنتجه PolicyRouter ويستهلكه NotificationManager: حملة | مباشر | تجاهل،
 * مع الأولويّة والسبب. (ليس enum لأنّه يحمل بيانات مصاحبة.)
 */
final class Decision
{
    private function __construct(
        public readonly bool $ignored,
        public readonly ?DispatchMode $dispatch,
        public readonly Priority $priority,
        public readonly string $reason,
    ) {}

    public static function campaign(Priority $priority): self
    {
        return new self(false, DispatchMode::Campaign, $priority, 'routed to campaign');
    }

    public static function direct(Priority $priority): self
    {
        return new self(false, DispatchMode::Direct, $priority, 'routed to direct');
    }

    public static function ignore(string $reason): self
    {
        return new self(true, null, Priority::Low, $reason);
    }

    /** تسمية القرار لسجلّ الأحداث (campaign|direct|ignore). */
    public function label(): string
    {
        return $this->ignored ? 'ignore' : $this->dispatch->value;
    }
}
