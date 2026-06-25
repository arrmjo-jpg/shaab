<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Advertising;

use App\Enums\AdDeviceClass;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * إسناد إبداع ↔ مساحة. القيود الإعداديّة (توافق النوع↔الموضع + منع التكرار) تُفرَض في
 * AttachAdPlacementAction (تحتاج نوعَي الإبداع/المساحة) — لا هنا. الإبداع المحذوف ناعماً
 * مُستبعَد عبر exists مشروط (whereNull deleted_at).
 */
class StoreAdPlacementRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'ad_creative_id' => ['required', 'integer', Rule::exists('ad_creatives', 'id')->whereNull('deleted_at')],
            'ad_zone_id' => ['required', 'integer', 'exists:ad_zones,id'],
            'weight' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'is_active' => ['sometimes', 'boolean'],
            'device_targets' => ['sometimes', 'nullable', 'array'],
            'device_targets.*' => [Rule::in(AdDeviceClass::values())],
        ];
    }
}
