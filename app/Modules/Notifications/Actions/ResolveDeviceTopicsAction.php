<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Models\Follow;
use App\Modules\Notifications\Enums\ChannelKey;
use App\Modules\Notifications\Enums\PreferenceScope;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\Support\TopicName;

/**
 * يحسب **الحالة المرغوبة** من topics لمستخدم/ضيف (السيرفر مصدر الحقيقة — قرار B). التطبيق يأخذ
 * القائمة ويزامن محليّاً. الأسبقيّة (Kill Switch منفصل تماماً — يحكم الإرسال لا الاشتراك):
 *   ① كتم عامّ (pref scope=global, channel=firebase, opted_in=false) ⇒ القائمة فارغة.
 *   ② Follow ⇒ المرشّحون (breaking_news + كيانات المتابعة).
 *   ③ كتم topic بعينه (pref scope=topic, scope_key=الـtopic) ⇒ يُزال المرشّح (متابعة + كتم = مُستبعَد).
 *
 * @return array<int,string>
 */
final class ResolveDeviceTopicsAction
{
    public function handle(?int $userId): array
    {
        $candidates = [TopicName::BREAKING_NEWS];

        if ($userId === null) {
            return $candidates; // ضيف: الافتراضيّ فقط (لا تفضيلات/متابعات)
        }

        $optedOut = NotificationPreference::query()
            ->where('user_id', $userId)
            ->where('channel', ChannelKey::Firebase->value)
            ->where('opted_in', false)
            ->get(['scope_type', 'scope_key']);

        // ① كتم عامّ ⇒ لا اشتراكات.
        if ($optedOut->contains(fn (NotificationPreference $p): bool => $p->scope_type === PreferenceScope::Global->value)) {
            return [];
        }

        // ② مرشّحو المتابعة.
        Follow::query()
            ->where('user_id', $userId)
            ->get(['followable_type', 'followable_id'])
            ->each(function (Follow $follow) use (&$candidates): void {
                $candidates[] = TopicName::follow($follow->followable_type, (int) $follow->followable_id);
            });

        $candidates = array_values(array_unique($candidates));

        // ③ ترشيح كتم topic بعينه.
        $mutedTopics = $optedOut
            ->where('scope_type', PreferenceScope::Topic->value)
            ->pluck('scope_key')
            ->all();

        if ($mutedTopics !== []) {
            $candidates = array_values(array_filter(
                $candidates,
                static fn (string $topic): bool => ! in_array($topic, $mutedTopics, true),
            ));
        }

        return $candidates;
    }
}
