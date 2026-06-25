<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings;

use App\Http\Requests\BaseFormRequest;

/**
 * اختبار اتصال التخزين البعيد قبل الحفظ/التفعيل — يقبل الاعتماديات من النموذج
 * (قد تكون غير محفوظة بعد). السرّ المُقنَّع (********) أو الفارغ يرجع للمحفوظ.
 */
class TestRemoteStorageRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'remote_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remote_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remote_bucket' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remote_region' => ['sometimes', 'nullable', 'string', 'max:50'],
            'remote_endpoint' => ['sometimes', 'nullable', 'string', 'max:255', UpdateMediaStorageSettingsRequest::safeEndpointRule()],
            'remote_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remote_use_path_style' => ['sometimes', 'boolean'],
        ];
    }
}
