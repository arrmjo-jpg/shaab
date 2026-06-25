<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\DispatchMode;
use App\Modules\Notifications\Enums\EventSource;
use App\Modules\Notifications\Enums\Priority;

/**
 * كتالوج الأحداث — مصدر الحقيقة الوحيد (code-authoritative، نمط SchedulerRegistry). dot-notation،
 * يُزامَن إلى notification_events. السلوك (dispatch/priority) والبيانات الوصفيّة (user_visible/
 * manual_dispatch) و**كتالوج المتغيّرات المدعومة (variables)** تُقرأ من هنا — لا بحث في الكود.
 *   is_user_visible          ⇒ يظهر للمستخدم/الإدارة (يُخفي التقنيّ مثل password_reset).
 *   supports_manual_dispatch ⇒ يجوز إطلاقه يدويًّا من واجهة الحملات.
 *   variables                ⇒ المتغيّرات النصّيّة المُوثَّقة للقوالب (لا متغيّرات عشوائيّة).
 */
final class EventCatalog
{
    /**
     * @return array<string, array{
     *   source: EventSource, priority: Priority, dispatch: DispatchMode,
     *   category: string, label: string, user_visible: bool, manual_dispatch: bool,
     *   variables: list<string>
     * }>
     */
    public static function all(): array
    {
        return [
            'article.published' => ['source' => EventSource::Domain, 'priority' => Priority::Normal, 'dispatch' => DispatchMode::Campaign, 'category' => 'content', 'label' => 'نشر مقال', 'user_visible' => true, 'manual_dispatch' => true, 'variables' => ['title', 'excerpt', 'url', 'category', 'author']],
            'article.breaking' => ['source' => EventSource::Domain, 'priority' => Priority::Critical, 'dispatch' => DispatchMode::Campaign, 'category' => 'content', 'label' => 'خبر عاجل', 'user_visible' => true, 'manual_dispatch' => true, 'variables' => ['title', 'excerpt', 'url', 'category']],
            'article.updated' => ['source' => EventSource::Domain, 'priority' => Priority::Low, 'dispatch' => DispatchMode::Campaign, 'category' => 'content', 'label' => 'تحديث مقال', 'user_visible' => true, 'manual_dispatch' => false, 'variables' => ['title', 'excerpt', 'url', 'category']],
            'video.published' => ['source' => EventSource::Domain, 'priority' => Priority::Normal, 'dispatch' => DispatchMode::Campaign, 'category' => 'content', 'label' => 'نشر فيديو', 'user_visible' => true, 'manual_dispatch' => true, 'variables' => ['title', 'url', 'category']],
            'reel.published' => ['source' => EventSource::Domain, 'priority' => Priority::Normal, 'dispatch' => DispatchMode::Campaign, 'category' => 'content', 'label' => 'نشر ريل', 'user_visible' => true, 'manual_dispatch' => true, 'variables' => ['title', 'url']],
            'poll.published' => ['source' => EventSource::Domain, 'priority' => Priority::Normal, 'dispatch' => DispatchMode::Campaign, 'category' => 'content', 'label' => 'نشر استطلاع', 'user_visible' => true, 'manual_dispatch' => true, 'variables' => ['title', 'question', 'url']],
            'broadcast.live' => ['source' => EventSource::Domain, 'priority' => Priority::High, 'dispatch' => DispatchMode::Campaign, 'category' => 'broadcast', 'label' => 'بثّ مباشر', 'user_visible' => true, 'manual_dispatch' => true, 'variables' => ['title', 'url']],
            'comment.reply' => ['source' => EventSource::Domain, 'priority' => Priority::Normal, 'dispatch' => DispatchMode::Direct, 'category' => 'engagement', 'label' => 'ردّ تعليق', 'user_visible' => false, 'manual_dispatch' => false, 'variables' => ['title', 'url', 'author']],
            'account.password_reset' => ['source' => EventSource::Domain, 'priority' => Priority::High, 'dispatch' => DispatchMode::Direct, 'category' => 'account', 'label' => 'استعادة كلمة المرور', 'user_visible' => false, 'manual_dispatch' => false, 'variables' => ['name', 'reset_url']],
            'match.reminder' => ['source' => EventSource::Scheduled, 'priority' => Priority::Normal, 'dispatch' => DispatchMode::Campaign, 'category' => 'sports', 'label' => 'تذكير مباراة', 'user_visible' => true, 'manual_dispatch' => false, 'variables' => ['match_name', 'kickoff_at', 'url']],
            'digest.daily' => ['source' => EventSource::Scheduled, 'priority' => Priority::Low, 'dispatch' => DispatchMode::Campaign, 'category' => 'digest', 'label' => 'ملخّص يوميّ', 'user_visible' => true, 'manual_dispatch' => false, 'variables' => ['date', 'count', 'url']],
            'system.alert' => ['source' => EventSource::System, 'priority' => Priority::Critical, 'dispatch' => DispatchMode::Direct, 'category' => 'system', 'label' => 'تنبيه نظاميّ', 'user_visible' => false, 'manual_dispatch' => false, 'variables' => ['kind', 'channel', 'error']],
        ];
    }

    public static function has(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /**
     * @return array{source:EventSource,priority:Priority,dispatch:DispatchMode,category:string,label:string,user_visible:bool,manual_dispatch:bool,variables:list<string>}|null
     */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** @return array<int,string> المتغيّرات المُوثَّقة للحدث (لمحرّر القوالب) */
    public static function variablesFor(string $key): array
    {
        return self::all()[$key]['variables'] ?? [];
    }

    /** @return array<int,string> الأحداث القابلة للإرسال اليدويّ (لواجهة الحملات) */
    public static function manualDispatchable(): array
    {
        return array_keys(array_filter(self::all(), fn (array $d): bool => $d['manual_dispatch']));
    }

    /** @return array<int,string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
