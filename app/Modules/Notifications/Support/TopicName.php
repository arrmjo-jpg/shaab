<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Enums\FollowableType;

/**
 * مولّد أسماء topics — المعيار الرسميّ الوحيد (تُولَّد من هنا حصراً، لا أسماء يدويّة).
 * النمط: {type}_{identifier} · lowercase snake_case · **مُعرّفات ثابتة** (IDs لا slugs حيث أمكن —
 * إعادة تسمية topic لاحقاً مؤلمة وتُيتّم الاشتراكات). لا نقاط (محجوزة لمفاتيح الأحداث).
 * يطابق قيود FCM ([a-zA-Z0-9-_.~%]). تغيير المخطّط مستقبلاً = تعديل هذا الملفّ وحده.
 */
final class TopicName
{
    public const BREAKING_NEWS = 'breaking_news';

    // ملاحظة: لا all_users/logged_users/guests كـtopics — هذه كوهورتات token-multicast
    // (السيرفر يحلّها من mobile_devices). الـtopics للتفضيل/المتابعة فقط (يصعب التراجع عنها).

    public static function category(int $id): string
    {
        return 'category_'.$id;
    }

    /** كيان رياضيّ (365): team_123 | competition_456 | player_789 | match_321 — من FollowableType. */
    public static function follow(FollowableType $type, int $id): string
    {
        return $type->value.'_'.$id;
    }

    public static function sport(string $slug): string
    {
        return 'sport_'.self::normalize($slug);
    }

    /** تعقيم مُعرّف نصّيّ ليطابق قيود FCM (احتياط للـslugs — الأرقام مفضّلة). */
    private static function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)) ?? '';
    }
}
