<?php

declare(strict_types=1);

namespace App\Support\Advertising;

/**
 * نتيجة فحص قابليّة نشر الحملة (Result Object) — كائن غير قابل للتغيير يُرجِعه
 * `AdCampaign::publishValidation()`، وهو **مصدر الحقيقة الوحيد** لشروط النشر. أيّ شاشة/API/زرّ
 * يريد معرفة «هل يمكن النشر؟» يستهلك هذا الكائن؛ يُمنع إعادة كتابة شروط النشر في أيّ مكان آخر.
 * التوسعة المستقبليّة (ميزانية/موافقة إدارة/صلاحية مُعلِن) تُضاف في تابع واحد فقط.
 */
final class CampaignPublishValidation
{
    /** @param array<string,mixed> $details */
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $reason = null,
        public readonly ?string $messageKey = null,
        public readonly array $details = [],
    ) {}

    public static function pass(): self
    {
        return new self(true);
    }

    /** @param array<string,mixed> $details */
    public static function fail(string $reason, string $messageKey, array $details = []): self
    {
        return new self(false, $reason, $messageKey, $details);
    }

    /** @return array{ok:bool,reason:?string,message_key:?string,details:array<string,mixed>} */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'reason' => $this->reason,
            'message_key' => $this->messageKey,
            'details' => $this->details,
        ];
    }
}
