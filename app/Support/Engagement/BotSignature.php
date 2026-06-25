<?php

declare(strict_types=1);

namespace App\Support\Engagement;

/**
 * كشف الزواحف/البوتات من ترويسة User-Agent (allow-list محافظ). يُستخدَم لمنع
 * تضخّم عدّاد المشاهدات بزيارات غير بشرية (محرّكات البحث، معاينات الروابط،
 * زواحف الذكاء الاصطناعي). محافظ عمداً: UA فارغ ليس بوتاً (نتجنّب false positives
 * على العملاء الشرعيّين/الاختبارات)؛ نطابق فقط رموز الزواحف المعروفة.
 */
final class BotSignature
{
    /** أنماط زواحف شائعة (محرّكات بحث + معاينات اجتماعية + زواحف AI + أدوات SEO). */
    private const PATTERN = '/'
        .'bot\b|bot\/|crawl|spider|slurp|mediapartners|'
        .'googlebot|google-?other|bingbot|bingpreview|yandex|baiduspider|duckduckbot|'
        .'applebot|petalbot|facebookexternalhit|facebot|ia_archiver|'
        .'gptbot|claudebot|claude-?web|ccbot|perplexitybot|amazonbot|bytespider|'
        .'semrush|ahrefs|mj12bot|dotbot|dataforseo|'
        .'embedly|quora link|pinterest|vkshare|telegrambot|whatsapp|skypeuripreview|discordbot'
        .'/i';

    public static function isBot(?string $userAgent): bool
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return false;
        }

        return preg_match(self::PATTERN, $userAgent) === 1;
    }
}
