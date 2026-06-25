<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

use App\Modules\Notifications\Contracts\ChannelDriver;
use App\Modules\Notifications\Enums\ChannelKey;
use InvalidArgumentException;

/**
 * سجلّ درايفرات القنوات — ChannelKey → ChannelDriver. Firebase + WhatsApp + Email (خلف عقد واحد).
 * يستهلكه Health Probe (يفحص الثلاثة تلقائيًّا) وطبقة الإرسال (يحلّ درايفر القناة).
 */
final class ChannelDriverRegistry
{
    /** @var array<string,ChannelDriver> */
    private array $drivers = [];

    public function __construct()
    {
        foreach ([new FirebaseDriver, new WhatsAppDriver, new EmailDriver] as $driver) {
            $this->drivers[$driver->key()->value] = $driver;
        }
    }

    public function for(ChannelKey $key): ChannelDriver
    {
        return $this->drivers[$key->value]
            ?? throw new InvalidArgumentException("no channel driver for: {$key->value}");
    }

    public function has(ChannelKey $key): bool
    {
        return isset($this->drivers[$key->value]);
    }

    /** @return array<int,ChannelDriver> */
    public function all(): array
    {
        return array_values($this->drivers);
    }
}
