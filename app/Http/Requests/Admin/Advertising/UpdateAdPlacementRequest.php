<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Advertising;

use App\Enums\AdDeviceClass;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * تعديل إسناد — الوزن/النشاط/أهليّة الجهاز فقط. لا تغيير للزوج (إبداع، مساحة): لتغييره
 * يُفصَل الإسناد ويُعاد (يحافظ على وضوح القيد الفريد وإعادة فحص التوافق).
 */
class UpdateAdPlacementRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'weight' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'is_active' => ['sometimes', 'boolean'],
            'device_targets' => ['sometimes', 'nullable', 'array'],
            'device_targets.*' => [Rule::in(AdDeviceClass::values())],
        ];
    }
}
