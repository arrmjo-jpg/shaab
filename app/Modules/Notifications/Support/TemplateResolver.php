<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\ChannelKey;
use App\Modules\Notifications\Models\NotificationTemplate;

/**
 * يحلّ قالب (event × channel × locale) بسلسلة fallback: المطابق للّغة ⇒ الافتراضيّ (is_default)
 * ⇒ أيّ قالب للقناة. null ⇒ لا قالب (الحملة تستعمل المحتوى الخام تدريجيًّا).
 */
final class TemplateResolver
{
    public function resolve(string $eventKey, ChannelKey $channel, string $locale): ?NotificationTemplate
    {
        $base = NotificationTemplate::query()->where('event_key', $eventKey)->where('channel', $channel->value);

        return (clone $base)->where('locale', $locale)->first()
            ?? (clone $base)->where('is_default', true)->first()
            ?? (clone $base)->first();
    }

    /**
     * القالب المربوط صراحةً في المصفوفة (template_id) — يُحمَّل **فقط إن طابق الحدث+القناة** (أمان ضدّ
     * ربط قديم/خاطئ بعد تغيير القالب). null ⇒ لا قالب مربوط صالح ⇒ يسقط النداء إلى resolve() بالـlocale.
     */
    public function resolveById(?int $id, string $eventKey, ChannelKey $channel): ?NotificationTemplate
    {
        if ($id === null) {
            return null;
        }

        return NotificationTemplate::query()
            ->whereKey($id)
            ->where('event_key', $eventKey)
            ->where('channel', $channel->value)
            ->first();
    }
}
