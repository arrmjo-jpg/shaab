<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Enums\ChannelKey;
use App\Modules\Notifications\Enums\DeliveryMode;
use App\Modules\Notifications\Models\NotificationEventChannel;
use App\Modules\Notifications\Models\NotificationEventType;
use App\Modules\Notifications\Support\EventCatalog;

/**
 * يُزامن EventCatalog (مصدر الحقيقة الكوديّ) إلى DB — **الكتالوج SoT لا قاعدة البيانات**:
 *   ① لكلّ حدث في الكتالوج: upsert notification_events (يحفظ enabled للأدمن) + ضمان صفّ مصفوفة
 *      لكلّ قناة (firstOrCreate، mode=disabled افتراضيًّا — لا يلمس إعداد الأدمن القائم).
 *   ② أحداث في DB غير موجودة في الكتالوج: **تُؤرشَف لا تُحذَف** (archived=true, enabled=false).
 * idempotent. يُشغَّل عند النشر (seeder/أمر) لا جدولة.
 */
final class SyncEventCatalogAction
{
    public function handle(): void
    {
        $keys = EventCatalog::keys();

        foreach (EventCatalog::all() as $key => $def) {
            $event = NotificationEventType::query()->updateOrCreate(
                ['key' => $key],
                [
                    'source' => $def['source']->value,
                    'category' => $def['category'],
                    'default_priority' => $def['priority']->value,
                    'is_user_visible' => $def['user_visible'],
                    'supports_manual_dispatch' => $def['manual_dispatch'],
                    'description' => $def['label'],
                    'archived' => false, // عاد للكتالوج ⇒ يُلغى أرشفته (enabled يبقى كما ضبط الأدمن)
                ],
            );

            foreach (ChannelKey::cases() as $channel) {
                NotificationEventChannel::query()->firstOrCreate(
                    ['event_id' => $event->id, 'channel' => $channel->value],
                    ['mode' => DeliveryMode::Disabled->value, 'channel_priority' => 100],
                );
            }
        }

        NotificationEventType::query()
            ->whereNotIn('key', $keys)
            ->update(['archived' => true, 'enabled' => false]);
    }
}
