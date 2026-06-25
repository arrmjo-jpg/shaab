<?php

declare(strict_types=1);

namespace App\Support\Video;

use App\Support\Media\ExternalVideoResolver;

/**
 * مُصنِّف مصدر الفيديو الخارجي لمكتبة الفيديو — يحصر الأنواع المسموح بها في النطاق
 * (youtube | vimeo | direct_mp4) فقط، رافضاً بقية مزوّدي ExternalVideoResolver
 * (TikTok/Instagram/Facebook/X) لأنها خارج نطاق مكتبة الفيديو.
 *
 * direct_mp4 يخضع لتحقّق صارم إضافي عبر Mp4HostAllowList (SafeUrl + allow-list).
 * يُحوّل قيمة المزوّد إلى source_type المُزال-التطبيع المخزَّن على الفيديو.
 */
final class VideoSourceResolver
{
    /** مزوّد ExternalVideoResolver ⇒ source_type في مكتبة الفيديو. */
    private const PROVIDER_TO_SOURCE = [
        'youtube' => 'youtube',
        'vimeo' => 'vimeo',
        'mp4' => 'direct_mp4',
    ];

    /**
     * يُصنّف رابطاً خارجياً إن كان مدعوماً في النطاق وآمناً، وإلا null.
     *
     * @return array{provider:string, source_type:string}|null
     */
    public static function classify(string $url): ?array
    {
        $resolved = ExternalVideoResolver::resolve($url);
        if ($resolved === null) {
            return null;
        }

        $provider = (string) $resolved['provider'];
        if (! isset(self::PROVIDER_TO_SOURCE[$provider])) {
            return null; // مزوّد خارج نطاق مكتبة الفيديو
        }

        // طبقة صرامة إضافية لـ MP4 المباشر: allow-list مضيفات (لا يكفي https العام).
        if ($provider === 'mp4' && ! Mp4HostAllowList::permits($url)) {
            return null;
        }

        return ['provider' => $provider, 'source_type' => self::PROVIDER_TO_SOURCE[$provider]];
    }

    public static function isValid(string $url): bool
    {
        return self::classify($url) !== null;
    }
}
