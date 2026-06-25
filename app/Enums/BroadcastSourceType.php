<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * نوع مصدر البثّ الخارجي (بثّ خارجي موثوق فقط — لا استضافة/ترميز/وسيط تشغيل).
 * كل نوع له قائمة مضيفات موثوقة في config('broadcast.allowed_hosts').
 */
enum BroadcastSourceType: string
{
    case Hls = 'hls';
    case Iptv = 'iptv';
    case YoutubeLive = 'youtube_live';
    case ExternalProvider = 'external_provider';
    case Icecast = 'icecast';
    case Shoutcast = 'shoutcast';

    public function label(): string
    {
        return __('broadcast.source.'.$this->value);
    }

    /** أنواع المصادر الصوتية (راديو) — لتجربة تشغيل صوتية فقط في الواجهة. */
    public function isAudio(): bool
    {
        return $this === self::Icecast || $this === self::Shoutcast;
    }

    /**
     * هل يمكن فحص صحّة المصدر خادمياً؟ HLS/IPTV (مانيفست) وIcecast/Shoutcast (وصول
     * صوتي) نعم. يوتيوب لايف/المزوّد الخارجي **لا** — لا نقطة صحّة عامة، ولا يمكن
     * التأكّد من حيّة بثّ يوتيوب دون واجهة YouTube Data API (قيد صادق موثّق).
     */
    public function isProbeable(): bool
    {
        return match ($this) {
            self::Hls, self::Iptv, self::Icecast, self::Shoutcast => true,
            self::YoutubeLive, self::ExternalProvider => false,
        };
    }

    /** مصدر مُضمَّن (embed) لا يُشغَّل عبر مشغّل HLS مباشر. */
    public function isEmbedded(): bool
    {
        return $this === self::YoutubeLive || $this === self::ExternalProvider;
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
