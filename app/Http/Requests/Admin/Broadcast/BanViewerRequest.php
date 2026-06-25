<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Broadcast;

/**
 * حظر مشاهد مؤقّتاً — يضيف للاستهداف مدّةً (دقائق، اختيارية بسقفٍ من الإعداد) وسبباً
 * اختيارياً. الانتهاء تلقائيّ عبر TTL (لا حظر دائم بالخطأ).
 */
class BanViewerRequest extends BroadcastViewerTargetRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'duration_minutes' => [
                'sometimes', 'nullable', 'integer', 'min:1',
                'max:'.max(1, (int) config('broadcast.presence.max_ban_minutes', 10080)),
            ],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
    }
}
