<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Media;

use App\Enums\MediaProcessingProfile;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * رفع أصل للمكتبة المركزية. حدود الحجم حسب النوع: صور 5MB، فيديو من الإعداد.
 */
class StoreMediaAssetRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $file = $this->file('file');
        $isVideo = $file && str_starts_with((string) $file->getMimeType(), 'video/');
        $isReel = $this->input('profile') === MediaProcessingProfile::Reel->value;

        // سقف الحجم: فيديو الريل أضيق من الفيديو العام (حارس تخزين عند الرفع).
        $videoMaxKb = $isReel
            ? (int) config('performance.media.reel_video_max_kb', 153600)
            : (int) config('performance.media.video_max_kb', 256000);
        $imageMaxKb = (int) config('performance.media.image_max_kb', 5120);
        $imageMaxDim = (int) config('performance.media.image_max_dimension', 8000);

        return [
            'file' => array_merge(
                ['required', 'file'],
                $isVideo
                    // المدّة/الأبعاد/الترميز تُفحَص بعد الـ probe في وظيفة الترميز
                    // (لا يمكن فحصها رخيصاً وقت الطلب). هنا: نوع الحاوية + الحجم.
                    ? ['mimetypes:video/mp4,video/webm', 'max:'.$videoMaxKb]
                    : [
                        'mimetypes:image/jpeg,image/png,image/webp',
                        'max:'.$imageMaxKb,
                        // حارس الأبعاد العملاقة (رخيص عبر getimagesize).
                        Rule::dimensions()->maxWidth($imageMaxDim)->maxHeight($imageMaxDim),
                    ],
            ),
            // ملف معالجة اختياري محايد للمحتوى (الفيديو فقط) — مثل reel.
            'profile' => ['sometimes', 'nullable', Rule::in(MediaProcessingProfile::values())],
        ];
    }
}
