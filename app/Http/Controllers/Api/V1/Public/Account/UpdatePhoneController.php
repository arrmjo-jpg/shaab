<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Account;

use App\Actions\Public\Account\UpdateUserPhoneAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Account\UpdatePhoneRequest;
use Illuminate\Http\JsonResponse;

/**
 * حفظ رقم الهاتف + اختيار الاشتراك في حملات واتساب للمستخدم المصادَق (نافذة ما بعد الدخول).
 */
class UpdatePhoneController extends Controller
{
    public function __invoke(UpdatePhoneRequest $request): JsonResponse
    {
        return (new UpdateUserPhoneAction)->handle($request->user(), $request->validated());
    }
}
