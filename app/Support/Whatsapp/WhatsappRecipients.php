<?php

declare(strict_types=1);

namespace App\Support\Whatsapp;

use App\Enums\WhatsappContactStatus;
use App\Models\WhatsappContact;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * مُحلِّل مستلمي الحملة — مصدر واحد لعدّ المعاينة والإرسال الفعليّ (يضمن تطابق الرقم
 * المعروض قبل الإرسال مع المُرسَل إليه فعلاً). مشترك subscribed فقط، فريد عبر المجموعات
 * (جهة في عدة مجموعات مختارة = مستلم واحد).
 *
 * @param  array<int,int>  $groupIds
 */
final class WhatsappRecipients
{
    public static function query(array $groupIds): Builder
    {
        return WhatsappContact::query()
            ->where('status', WhatsappContactStatus::Subscribed->value)
            ->whereHas('groups', fn (Builder $q) => $q->whereIn('whatsapp_groups.id', $groupIds));
    }

    /** @param  array<int,int>  $groupIds */
    public static function count(array $groupIds): int
    {
        if ($groupIds === []) {
            return 0;
        }

        return self::query($groupIds)->count();
    }
}
