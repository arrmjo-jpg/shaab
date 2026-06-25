<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Advertising;

use App\Enums\AdCampaignStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * طلب انتقال حالة الحملة. يتحقّق من أنّ الحالة قيمة معروفة فقط؛ شرعيّة الانتقال نفسها
 * (آلة الحالة + حارس النافذة) تُفرَض في ChangeAdCampaignStatusAction.
 */
class ChangeAdCampaignStatusRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(AdCampaignStatus::values())],
        ];
    }
}
