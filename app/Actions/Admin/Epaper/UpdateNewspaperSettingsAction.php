<?php

declare(strict_types=1);

namespace App\Actions\Admin\Epaper;

use App\Settings\NewspaperSettings;
use App\Support\Audit\SettingsAudit;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تحديث إعدادات الجريدة الرقمية (تفعيل/تعطيل الوحدة + الاسم المعروض). تفعيل الوحدة
 * قرار على مستوى الإعدادات (settings.edit) لا على مستوى محرّر الأعداد.
 */
class UpdateNewspaperSettingsAction
{
    /** @param  array<string,mixed>  $data */
    public function handle(array $data): JsonResponse
    {
        $settings = app(NewspaperSettings::class);
        $settings->enabled = (bool) $data['enabled'];
        $settings->display_name = (string) $data['display_name'];
        $settings->subscribe_url = (string) ($data['subscribe_url'] ?? '');
        $settings->save();

        // حالة غير Eloquent (Spatie Settings) ⇒ تدقيق يدويّ بأسماء المفاتيح فقط (لا أسرار).
        SettingsAudit::log('newspaper', array_keys($data));

        // إبطال واجهة Next: newspaper_enabled يظهر في /site (رابط «الجريدة الرقمية» بالهيدر).
        FrontendRevalidate::tags(['site-settings']);

        return ApiResponse::success(__('epaper.settings_updated'), [
            'enabled' => $settings->enabled,
            'display_name' => $settings->display_name,
            'subscribe_url' => $settings->subscribe_url,
        ]);
    }
}
