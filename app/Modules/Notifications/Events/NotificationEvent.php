<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Events;

use App\Modules\Notifications\Enums\EventSource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * الحدث الموجَّه العامّ — مصدر الحقيقة الوحيد لكلّ الأحداث (Event-First). يحمل المفتاح
 * (يُتحقَّق مقابل EventCatalog) + المصدر + الحمولة + وقت الوقوع. يستهلكه Policy Router واحد.
 * بديلٌ عن أصناف per-type المتطابقة (كنّ يختلفن بسلسلة نصّ واحدة — لا فائدة من تعدّدهنّ).
 * إطلاق: NotificationEvent::dispatch('article.breaking', EventSource::Domain, ['id' => 5]).
 */
final class NotificationEvent
{
    use Dispatchable;

    /** @param array<string,mixed> $payload */
    public function __construct(
        public readonly string $eventKey,
        public readonly EventSource $source,
        public readonly array $payload = [],
        public readonly ?CarbonImmutable $occurredAt = null,
    ) {}
}
