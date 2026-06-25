<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * إعدادات الجريدة الرقمية على مستوى الموقع. المفتاح enabled هو بوّابة المنتج:
 * عند تعطيله تختفي وحدات الجريدة من الإدارة والواجهة العامة (يُفرَض في الطبقات).
 */
class NewspaperSettings extends Settings
{
    public bool $enabled;

    public string $display_name;

    /** رابط صفحة الاشتراك (CTA في صفحة التشويق). فارغ ⇒ لا يُعرَض زرّ. */
    public string $subscribe_url;

    public static function group(): string
    {
        return 'newspaper';
    }
}
