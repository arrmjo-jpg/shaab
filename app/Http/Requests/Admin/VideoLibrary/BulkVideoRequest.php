<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\VideoLibrary;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * عملية جماعية على فيديوهات المكتبة. النوع يحدّد المعاملات المطلوبة. التحقّق هنا
 * بنيوي فقط؛ صلاحية كل عملية وثوابت كل عنصر (الجاهزية…) تُفرَض في الـ Action.
 */
class BulkVideoRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $action = (string) $this->input('action');

        $rules = [
            'action' => ['required', Rule::in(['publish', 'unpublish', 'feature', 'move_category', 'add_to_playlist', 'delete'])],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'exists:videos,id'],
        ];

        if ($action === 'feature') {
            $rules['value'] = ['required', 'boolean'];
        }

        if ($action === 'move_category') {
            // يجب إرسال الحقل (يُسمح أن يكون null لتفريغ التصنيف) — present لا required.
            $rules['video_category_id'] = ['present', 'nullable', 'integer', 'exists:video_categories,id'];
        }

        if ($action === 'add_to_playlist') {
            $rules['playlist_id'] = ['required', 'integer', 'exists:video_playlists,id'];
        }

        return $rules;
    }
}
