<?php

declare(strict_types=1);

namespace App\Support\Whatsapp;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * تطبيع/تحقّق أرقام الهاتف الدولية عبر libphonenumber (حزمة propaganistas/laravel-phone).
 * العقد: حقل واحد phone بصيغة E.164 كاملة (+9627…)، ويُرفَض أي رقم محلي بلا مفتاح دولة
 * (لا تخمين دولة افتراضية إطلاقاً). 00 الدولية تُحوَّل إلى + والفواصل الشكلية تُسقَط.
 */
final class PhoneNumber
{
    /** يعيد E.164 الموحَّد أو null إن كان الرقم غير صالح/غير دولي. */
    public static function normalize(?string $raw): ?string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        // إسقاط الفواصل الشكلية الشائعة ثم توحيد البادئة الدولية 00 إلى +.
        $value = preg_replace('/[\s\-().]/u', '', $value) ?? '';
        if (str_starts_with($value, '00')) {
            $value = '+'.substr($value, 2);
        }

        // دولي فقط: يجب أن يبدأ بـ + (رفض الأرقام المحلية صراحةً).
        if (! str_starts_with($value, '+')) {
            return null;
        }

        try {
            $util = PhoneNumberUtil::getInstance();
            $parsed = $util->parse($value, null);

            return $util->isValidNumber($parsed)
                ? $util->format($parsed, PhoneNumberFormat::E164)
                : null;
        } catch (NumberParseException) {
            return null;
        }
    }

    public static function isValid(?string $raw): bool
    {
        return self::normalize($raw) !== null;
    }
}
