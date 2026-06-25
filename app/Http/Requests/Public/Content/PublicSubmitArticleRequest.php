<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Content;

use App\Enums\ArticleStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * إرسال مقال الكاتب للمراجعة (نطاق عام — V1).
 *
 * النسخة العامة من TransitionArticleRequest: تحصر الهدف بـ submitted فقط
 * (الكاتب لا يَنشُر/يجدول/يؤرشف). الحصر الفعلي يُفرَض أيضاً في
 * ArticleWorkflowGuard::WRITER_ALLOWED + فحص الملكية (لا يُلمسان).
 * لا published_at — لا جدولة للكاتب.
 */
class PublicSubmitArticleRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([ArticleStatus::Submitted->value])],
        ];
    }
}
