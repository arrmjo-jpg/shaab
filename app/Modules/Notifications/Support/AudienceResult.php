<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\AudienceType;
use JsonSerializable;

/**
 * نتيجة جمهور — **سبيك بيانات نقيّ قابل للتسلسل** (لا Builders، لا اتّصال DB، لا closures). يصف
 * «مَن» تصريحيّاً فيعبر بأمان حدود الطوابير/الكاش/الجدولة. يُحوّله ChannelBinder إلى RecipientBatch
 * حسب القناة. الاستعلامات الحيّة تُبنى **وقت التنفيذ** من هذا السبيك عبر AudienceResolver (عابرة،
 * تُستهلَك فورًا، لا تُسلسَل أبدًا).
 *   type           نوع الجمهور (resolver يبني استعلامه).
 *   params         معاملات قابلة للتسلسل فقط (team_id, platform, category_id…).
 *   topic          اسم topic للبثّ (push) إن كان الجمهور قابلًا له — وإلّا null.
 *   estimatedCount تقدير العدّ (للمعاينة)، يُحسب وقت describe().
 */
final class AudienceResult implements JsonSerializable
{
    /** @param  array<string,scalar|list<scalar>>  $params */
    private function __construct(
        public readonly AudienceType $type,
        public readonly array $params,
        public readonly ?string $topic,
        public readonly ?int $estimatedCount,
    ) {}

    /** @param  array<string,scalar|list<scalar>>  $params */
    public static function topic(AudienceType $type, string $topic, array $params = [], ?int $estimatedCount = null): self
    {
        return new self($type, $params, $topic, $estimatedCount);
    }

    /** @param  array<string,scalar|list<scalar>>  $params */
    public static function cohort(AudienceType $type, array $params = [], ?int $estimatedCount = null): self
    {
        return new self($type, $params, null, $estimatedCount);
    }

    public function hasTopic(): bool
    {
        return $this->topic !== null;
    }

    /** @return array{type:string,params:array<string,scalar|list<scalar>>,topic:?string,estimated_count:?int} */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'params' => $this->params,
            'topic' => $this->topic,
            'estimated_count' => $this->estimatedCount,
        ];
    }

    /** @param  array{type:string,params?:array<string,scalar|list<scalar>>,topic?:?string,estimated_count?:?int}  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            AudienceType::from($data['type']),
            $data['params'] ?? [],
            $data['topic'] ?? null,
            $data['estimated_count'] ?? null,
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
