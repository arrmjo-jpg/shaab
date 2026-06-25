<?php

declare(strict_types=1);

namespace App\Actions\Admin\Epaper;

use App\Settings\NewspaperSettings;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * قراءة إعدادات الجريدة الرقمية (مفتاح التفعيل + الاسم المعروض). غير حسّاسة — تُقرأ
 * من أي مشرف مصادَق كي يستطيع التنقّل تقييد ظهور قسم الجريدة على enabled (بصرف النظر
 * عن امتلاكه صلاحيات الإعدادات).
 */
class ShowNewspaperSettingsAction
{
    public function handle(): JsonResponse
    {
        $settings = app(NewspaperSettings::class);

        return ApiResponse::success(__('epaper.settings_shown'), [
            'enabled' => $settings->enabled,
            'display_name' => $settings->display_name,
            'subscribe_url' => $settings->subscribe_url,
        ]);
    }
}
