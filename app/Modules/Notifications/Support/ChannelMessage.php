<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

/**
 * رسالة مُصيَّرة جاهزة للقناة (channel-agnostic) — يُنتجها المُنسّق من القالب ويُسلّمها
 * للدرايفر. الدرايفر يحوّلها إلى حمولته الأصليّة (FCM notification+data، email، نصّ واتساب).
 */
final class ChannelMessage
{
    /** @param array<string,string> $data حمولة بيانات إضافيّة (يدمجها الدرايفر مع الرابط العميق) */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $imageUrl = null,
        public readonly ?DeepLink $deepLink = null,
        public readonly array $data = [],
        public readonly ?string $locale = null,
    ) {}
}
