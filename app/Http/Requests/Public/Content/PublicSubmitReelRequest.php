<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Content;

use App\Enums\ReelStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * إرسال ريل الكاتب للمراجعة (نطاق عام — V1).
 *
 * النسخة العامة من انتقال الحالة: تحصر الهدف بـ submitted فقط (الكاتب لا
 * يَنشُر/يجدول/يؤرشف). الحصر الفعلي يُفرَض أيضاً في
 * ReelWorkflowGuard::WRITER_ALLOWED + فحص الملكية (لا يُلمسان).
 * لا published_at — لا جدولة للكاتب.
 */
class PublicSubmitReelRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([ReelStatus::Submitted->value])],
        ];
    }
}
