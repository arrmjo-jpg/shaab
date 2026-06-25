<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Health;

use App\Modules\Notifications\Channels\ChannelDriverRegistry;
use App\Modules\Notifications\Contracts\ChannelDriver;
use App\Modules\Notifications\Enums\ChannelHealthState;
use App\Modules\Notifications\Enums\ChannelKey;
use App\Modules\Notifications\Enums\EventSource;
use App\Modules\Notifications\Events\NotificationEvent;
use App\Modules\Notifications\Models\NotificationChannelHealth;

/**
 * فحص صحّة القنوات — يستدعي driver.health()، يحسب effective_state = resolve(enabled, configured,
 * healthy)، يُديم notification_channel_health، ويُطلق system.alert عند **الدخول** في degraded (عبر
 * NotificationManager — تغذية ذاتيّة). يُدار بأمر مجدول (notifications:probe-channels، كلّ 10د).
 */
final class ChannelHealthProbe
{
    public function __construct(private readonly ChannelDriverRegistry $drivers) {}

    public function probeAll(): void
    {
        foreach ($this->drivers->all() as $driver) {
            $this->probe($driver);
        }
    }

    public function probe(ChannelDriver $driver): NotificationChannelHealth
    {
        $health = $driver->health();
        $state = ChannelHealthState::resolve($driver->enabled(), $health->configured, $health->healthy);

        $row = NotificationChannelHealth::query()->firstOrNew(['channel' => $driver->key()->value]);
        $previous = $row->effective_state; // ?ChannelHealthState (مُكسَّر؛ null للصفّ الجديد)

        $row->effective_state = $state;
        $row->configured = $health->configured;
        $row->healthy = $health->healthy;
        $row->last_error = $health->lastError;
        $row->latency_ms = $health->latencyMs;
        $row->last_checked_at = now();
        if ($state === ChannelHealthState::Healthy) {
            $row->last_ok_at = now();
            $row->consecutive_failures = 0;
        } else {
            $row->consecutive_failures = ((int) $row->consecutive_failures) + 1;
        }
        $row->save();

        $this->alertOnDegradation($driver->key(), $previous, $state, $health->lastError);

        return $row;
    }

    /** تنبيه فقط عند الدخول في degraded (فشل تشغيليّ) — لا spam، ولا تنبيه للحالات الإداريّة. */
    private function alertOnDegradation(ChannelKey $channel, ?ChannelHealthState $previous, ChannelHealthState $current, ?string $error): void
    {
        if ($current !== ChannelHealthState::Degraded || $previous === ChannelHealthState::Degraded) {
            return;
        }

        NotificationEvent::dispatch('system.alert', EventSource::System, [
            'kind' => 'channel_degraded',
            'channel' => $channel->value,
            'error' => $error,
        ]);
    }
}
