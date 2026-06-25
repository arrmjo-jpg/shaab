<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\ChannelKey;
use App\Modules\Notifications\Enums\PreferenceScope;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * يستبعد المستلمين الذين ألغوا الاشتراك (opt-out) من استعلام جمهور **عند الربط** (channel-aware،
 * يطابق دلالة الكتم العامّ في ResolveDeviceTopicsAction: scope=global · channel=القناة · opted_in=false).
 * المستخدمون بلا user_id (ضيوف) لا تفضيلات لهم ⇒ يبقَون. التفضيلات الدقيقة (category/event) = v1.2.
 */
final class PreferenceFilter
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  string  $userIdColumn  العمود الحامل لـuser_id في الجدول المُستعلَم (قد يكون null للضيوف)
     * @return Builder<TModel>
     */
    public static function excludeOptedOut(Builder $query, string $userIdColumn, ChannelKey $channel): Builder
    {
        return $query->whereNotExists(function (QueryBuilder $sub) use ($userIdColumn, $channel): void {
            $sub->select(DB::raw(1))
                ->from('notification_preferences')
                ->whereColumn('notification_preferences.user_id', $userIdColumn)
                ->where('notification_preferences.channel', $channel->value)
                ->where('notification_preferences.scope_type', PreferenceScope::Global->value)
                ->where('notification_preferences.opted_in', false);
        });
    }
}
