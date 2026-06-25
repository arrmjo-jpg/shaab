<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Content;

use App\Http\Requests\BaseFormRequest;

/**
 * طلب نبضة منارة المشاهدة — يتطلّب رمزاً موقّعاً صدر من استجابة التفاصيل
 * (meta.view_token). صحّة التوقيع/الانتهاء/المطابقة تُتحقَّق في المتحكّم.
 */
class ViewBeaconRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:512'],
        ];
    }
}
