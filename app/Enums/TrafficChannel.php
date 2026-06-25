<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Http\Request;

/**
 * قناة الزيارة (تصنيف خشن لمصدر المشاهدة) — يُحتسب عند منارة المشاهدة من UTM ثم
 * المُحيل (Referer) مقابل مضيف التطبيق وقوائم محرّكات بحث/تواصل معروفة. تقريبيّ عمداً
 * (نسخة v1 الصادقة): لا نخترع تفاصيل دقيقة لا نملك بياناتها.
 */
enum TrafficChannel: string
{
    case Direct = 'direct';
    case Internal = 'internal';
    case Search = 'search';
    case Social = 'social';
    case Referral = 'referral';

    /** علامات نطاق محرّكات البحث (مطابقة كلصيقة نطاق). */
    private const SEARCH_LABELS = ['google', 'bing', 'yahoo', 'duckduckgo', 'yandex', 'baidu', 'ecosia', 'qwant'];

    /** علامات نطاق منصّات التواصل (مطابقة كلصيقة نطاق). */
    private const SOCIAL_LABELS = ['facebook', 'instagram', 'youtube', 'tiktok', 'linkedin', 'reddit', 'pinterest', 'snapchat', 'telegram', 'twitter', 'threads', 'mastodon'];

    /** مضيفات تواصل مختصرة (مطابقة دقيقة أو لاحقة نطاق). */
    private const SOCIAL_EXACT = ['x.com', 't.co', 'fb.com', 'youtu.be', 'wa.me', 'lnkd.in', 't.me'];

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    /** اسم عمود المشاهدات المقابل في content_daily_stats. */
    public function column(): string
    {
        return 'views_'.$this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'مباشر',
            self::Internal => 'داخلي',
            self::Search => 'بحث',
            self::Social => 'تواصل اجتماعي',
            self::Referral => 'إحالة',
        };
    }

    /**
     * تصنيف خشن لقناة الزيارة من الطلب: UTM صريح يتقدّم (حملات)، وإلا المُحيل (Referer)
     * مقابل مضيف التطبيق (داخلي) ثم قوائم البحث/التواصل، وإلا إحالة عامّة؛ لا مُحيل ⇒ مباشر.
     */
    public static function fromRequest(Request $request): self
    {
        $medium = strtolower(trim((string) $request->query('utm_medium', '')));
        $source = strtolower(trim((string) $request->query('utm_source', '')));
        if ($medium !== '' || $source !== '') {
            return self::fromUtm($medium, $source);
        }

        $referer = (string) $request->headers->get('referer', '');
        if ($referer === '') {
            return self::Direct;
        }

        $host = strtolower((string) parse_url($referer, PHP_URL_HOST));
        if ($host === '') {
            return self::Direct;
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($appHost !== '' && ($host === $appHost || str_ends_with($host, '.'.$appHost))) {
            return self::Internal;
        }

        if (self::hasLabel($host, self::SEARCH_LABELS)) {
            return self::Search;
        }

        if (self::hasLabel($host, self::SOCIAL_LABELS) || self::isExact($host, self::SOCIAL_EXACT)) {
            return self::Social;
        }

        return self::Referral;
    }

    private static function fromUtm(string $medium, string $source): self
    {
        if (in_array($medium, ['organic', 'search'], true) || in_array($source, self::SEARCH_LABELS, true)) {
            return self::Search;
        }

        if (str_starts_with($medium, 'social') || in_array($source, self::SOCIAL_LABELS, true)) {
            return self::Social;
        }

        // email/cpc/affiliate/referral/… ⇒ إحالة (تصنيف خشن؛ لا نخترع قنوات دقيقة).
        return self::Referral;
    }

    /** هل لأحد ألصقة المضيف (مقسّمة على «.») قيمة في القائمة؟ (google.com, news.google.co.uk). */
    private static function hasLabel(string $host, array $labels): bool
    {
        $parts = explode('.', $host);
        foreach ($labels as $label) {
            if (in_array($label, $parts, true)) {
                return true;
            }
        }

        return false;
    }

    private static function isExact(string $host, array $hosts): bool
    {
        foreach ($hosts as $h) {
            if ($host === $h || str_ends_with($host, '.'.$h)) {
                return true;
            }
        }

        return false;
    }
}
