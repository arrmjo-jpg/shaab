<?php

declare(strict_types=1);

namespace App\Support\Engagement;

use App\Models\Article;
use App\Models\Reel;
use App\Models\Video;
use Illuminate\Database\Eloquent\Model;

/**
 * يربط رمز نوع نظيف من الـ API (مثل "article") بصنف النموذج الفعلي — دون فرض
 * morph map عام (تجنّباً لكسر علاقات polymorphic القائمة كالوسوم/التدقيق).
 *
 * إضافة نوع مستقبلي = صفّ في MAP + فرع في find() مع شرط ظهوره العام
 * (reel/video/stream/printed_edition).
 */
final class EngageableResolver
{
    /** @var array<string,class-string> */
    private const MAP = [
        'article' => Article::class,
        'reel' => Reel::class,
        'video' => Video::class,
    ];

    public static function isSupported(string $type): bool
    {
        return isset(self::MAP[$type]);
    }

    /** @return class-string|null */
    public static function classFor(string $type): ?string
    {
        return self::MAP[$type] ?? null;
    }

    /** @return array<int,string> */
    public static function types(): array
    {
        return array_keys(self::MAP);
    }

    /**
     * يحمّل الهدف القابل للتفاعل مطبّقاً شرط الظهور العام لكل نوع (لا تفاعل مع
     * محتوى غير منشور). يُعيد null إن لم يوجد أو لم يكن متاحاً للعموم.
     */
    public static function find(string $type, int $id): ?Model
    {
        return match ($type) {
            'article' => Article::query()->published()->whereKey($id)->first(),
            'reel' => Reel::query()->published()->whereKey($id)->first(),
            // الفيديو: قابل للعرض (منشور + قابل للتشغيل + عام/غير مُدرَج) — لا تفاعل
            // مع مسودة/مؤرشف/خاص أو وسائط غير جاهزة.
            'video' => Video::query()->viewable()->whereKey($id)->first(),
            default => null,
        };
    }
}
