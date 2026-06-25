<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\VideoLibrary;

use App\Http\Requests\BaseFormRequest;
use App\Models\Video;
use Illuminate\Validation\Validator;

class AttachPlaylistVideosRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // محدود بـ max لمنع إساءة المصفوفات الضخمة (مرآة انضباط BulkVideoRequest).
        return [
            'video_ids' => ['required', 'array', 'min:1', 'max:200'],
            'video_ids.*' => ['integer'],
        ];
    }

    /**
     * تحقّق الوجود دفعةً واحدة (استعلام واحد) بدل exists لكل عنصر — يمنع N استعلام.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ids = collect($this->input('video_ids', []))
                ->filter(fn ($v): bool => is_numeric($v))
                ->map(fn ($v): int => (int) $v)
                ->unique();

            if ($ids->isEmpty()) {
                return;
            }

            $existing = Video::query()->whereIn('id', $ids->all())->pluck('id');
            if ($ids->diff($existing)->isNotEmpty()) {
                $validator->errors()->add('video_ids', __('validation.exists', ['attribute' => 'video_ids']));
            }
        });
    }
}
