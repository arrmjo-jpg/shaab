<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Activity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * عنصر سجل نشاط للعرض الإداري الشامل. يُعقّم القيم الحسّاسة دائماً.
 */
class AdminActivityResource extends JsonResource
{
    private static function isSensitive(string $key): bool
    {
        $k = strtolower($key);

        foreach (['password', 'secret', 'token', 'api_key'] as $needle) {
            if (str_contains($k, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,mixed> $map */
    private static function sanitize(array $map): array
    {
        $out = [];
        foreach ($map as $key => $value) {
            $out[$key] = self::isSensitive((string) $key) ? '••••••' : $value;
        }

        return $out;
    }

    public function toArray(Request $request): array
    {
        $props = $this->properties instanceof Collection
            ? $this->properties->all()
            : (array) $this->properties;

        $changes = [];
        foreach (['attributes', 'old'] as $bucket) {
            if (isset($props[$bucket]) && is_array($props[$bucket])) {
                $changes[$bucket] = self::sanitize($props[$bucket]);
            }
        }

        // سياق إضافي آمن (auth/settings) — مفاتيح غير حسّاسة فقط
        $context = [];
        foreach ($props as $k => $v) {
            if (in_array($k, ['attributes', 'old'], true)) {
                continue;
            }
            $context[$k] = self::isSensitive((string) $k) ? '••••••' : $v;
        }

        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'event' => $this->event,
            'description' => $this->description,
            'subject_type' => $this->subject_type ? class_basename($this->subject_type) : null,
            'subject_id' => $this->subject_id,
            'causer' => $this->whenLoaded('causer', fn () => $this->causer ? [
                'id' => $this->causer->id,
                'name' => $this->causer->name,
            ] : null),
            'changes' => $changes,
            'context' => $context,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
