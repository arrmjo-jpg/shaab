<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\VideoLibrary;

use App\Http\Requests\BaseFormRequest;

class ReorderPlaylistVideosRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // محدود بـ max لمنع إساءة المصفوفات الضخمة؛ الأصناف الدخيلة تُتجاهَل في الـ Action.
        // يجب أن يساوي/يفوق سقف تحميل المحرّر (VideoPlaylistController::MAX_EDITOR_VIDEOS=500)
        // لأن الواجهة تُرسل ترتيب القائمة المُحمَّلة كاملةً — لا قطع للترتيب على القوائم الكبيرة.
        return [
            'ordered_ids' => ['required', 'array', 'min:1', 'max:500'],
            'ordered_ids.*' => ['integer'],
        ];
    }
}
