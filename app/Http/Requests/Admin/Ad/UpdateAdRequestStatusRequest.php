<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Ad;

use App\Enums\AdRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * تغيير حالة طلب إعلان (الصلاحية على المسار). الأهداف: كامل البايبلاين عدا «new» الأوّليّة
 * (إعادة الفتح = closed→contacted مثلاً). completed/rejected/closed منفصلة (لا دمج).
 */
class UpdateAdRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([
                AdRequestStatus::Contacted->value,
                AdRequestStatus::Negotiating->value,
                AdRequestStatus::Completed->value,
                AdRequestStatus::Rejected->value,
                AdRequestStatus::Closed->value,
            ])],
        ];
    }
}
