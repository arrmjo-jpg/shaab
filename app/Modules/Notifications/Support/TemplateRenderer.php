<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

/**
 * مُصيّر قوالب بسيط ومستقلّ (قابل للاختبار) — استبدال `{{var}}` مباشر وآمن. **لا Blade، لا eval،
 * لا view rendering، لا دوال/تعابير.** متغيّرات نصّيّة فقط؛ المفقود ⇒ سلسلة فارغة؛ غير النصّيّ ⇒ فارغ.
 * النمط الوحيد المسموح: اسم متغيّر [a-zA-Z0-9_] بين قوسين مزدوجين (مسافات اختياريّة).
 */
final class TemplateRenderer
{
    /** @param  array<string,mixed>  $variables */
    public function render(?string $template, array $variables): string
    {
        if ($template === null || $template === '') {
            return '';
        }

        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            static function (array $match) use ($variables): string {
                $value = $variables[$match[1]] ?? '';

                return is_scalar($value) ? (string) $value : '';
            },
            $template,
        );
    }
}
