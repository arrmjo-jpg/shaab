<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Notifications\Enums\ChannelKey;
use App\Modules\Notifications\Support\EventCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * إنشاء/تحديث قالب. يفرض **المتغيّرات الموثّقة فقط**: أي {{var}} في title/body/deep_link_value يجب
 * أن يكون ضمن EventCatalog::variablesFor(event_key) — لا متغيّرات عشوائيّة (قرار مقفل).
 * البوّابة على المسار (permission:notifications.manage).
 */
final class TemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'event_key' => ['required', Rule::in(EventCatalog::keys())],
            'channel' => ['required', Rule::in(array_column(ChannelKey::cases(), 'value'))],
            'locale' => ['nullable', 'string', 'max:10'],
            'title' => ['nullable', 'string', 'max:300'],
            'body' => ['nullable', 'string', 'max:3000'],
            'image_strategy' => ['nullable', Rule::in(['none', 'content'])],
            'deep_link_type' => ['nullable', 'string', 'max:30'],
            'deep_link_value' => ['nullable', 'string', 'max:500'],
            'variables' => ['nullable', 'array'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $key = $this->input('event_key');
            if (! is_string($key) || ! EventCatalog::has($key)) {
                return;
            }

            $allowed = EventCatalog::variablesFor($key);
            $used = [];
            foreach (['title', 'body', 'deep_link_value'] as $field) {
                preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', (string) $this->input($field, ''), $m);
                $used = array_merge($used, $m[1]);
            }

            $undocumented = array_values(array_unique(array_diff($used, $allowed)));
            if ($undocumented !== []) {
                $v->errors()->add(
                    'variables',
                    'متغيّرات غير موثّقة لهذا الحدث: '.implode('، ', $undocumented).'. المسموح: '.implode('، ', $allowed),
                );
            }
        });
    }
}
