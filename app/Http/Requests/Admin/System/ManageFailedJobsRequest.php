<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\System;

use App\Http\Requests\BaseFormRequest;

/**
 * طلب إدارة المهام الفاشلة (إعادة محاولة/حذف): إمّا قائمة معرّفات UUID أو all=true.
 */
class ManageFailedJobsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'all' => ['sometimes', 'boolean'],
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['string', 'max:64'],
        ];
    }
}
