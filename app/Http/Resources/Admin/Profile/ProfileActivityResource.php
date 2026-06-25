<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Profile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * نشاط المستخدم — حمولة مُعقّمة بقائمة بيضاء صارمة.
 * لا تُكشَف بنية الخصائص الداخلية الخام إطلاقاً.
 */
class ProfileActivityResource extends JsonResource
{
    /** المفاتيح الآمنة الوحيدة المسموح عرضها للمستخدم. */
    private const SAFE_KEYS = ['source', 'ip', 'user_agent', 'requested_email', 'timestamp'];

    public function toArray(Request $request): array
    {
        $props = $this->properties instanceof Collection
            ? $this->properties->all()
            : (array) $this->properties;

        $context = [];
        foreach (self::SAFE_KEYS as $key) {
            if (! array_key_exists($key, $props) || $props[$key] === null) {
                continue;
            }
            $value = $props[$key];
            if ($key === 'user_agent') {
                $value = mb_substr((string) $value, 0, 180);
            }
            $context[$key] = is_scalar($value) ? $value : null;
        }

        return [
            'id' => $this->id,
            'event' => $this->event,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'context' => $context,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
