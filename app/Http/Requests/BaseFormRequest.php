<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseFormRequest extends FormRequest
{
    /**
     * تجاوز استجابة الـ validation الافتراضية لتتوافق مع عقد الـ API.
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            ApiResponse::error(
                __('api.validation_failed'),
                $validator->errors()->toArray(),
                422
            )
        );
    }
}
