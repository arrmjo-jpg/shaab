<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Http\Request;

/**
 * مصدر الطلب الموثوق — يُرسَل عبر ترويسة X-Client-Source.
 * قائمة بيضاء صارمة؛ أي قيمة خارجها تُعامَل كـ "غير معروف".
 */
enum ClientSource: string
{
    case AdminWeb = 'admin_web';
    case AdminFlutter = 'admin_flutter';
    case PublicWeb = 'public_web';
    case PublicMobile = 'public_mobile';
    case ApiDirect = 'api_direct';

    /** الترويسة الموثوقة لتمرير المصدر. */
    public const HEADER = 'X-Client-Source';

    /** يحلّ المصدر من الترويسة مع التحقق من القائمة البيضاء. */
    public static function fromRequest(?Request $request = null): ?self
    {
        $request ??= request();
        $raw = trim((string) $request->header(self::HEADER));

        return $raw === '' ? null : self::tryFrom($raw);
    }

    /** القيمة النصّية للتخزين/السجل (أو "unknown"). */
    public static function key(?self $source): string
    {
        return $source?->value ?? 'unknown';
    }

    /** تسمية بشرية مترجمة (تُستخدم في البريد). */
    public function label(): string
    {
        return __('auth.reset_source.'.$this->value);
    }

    public static function labelFor(?self $source): string
    {
        return $source?->label() ?? __('auth.reset_source.unknown');
    }

    /** تسمية من قيمة نصّية مخزَّنة (تتحقق من القائمة البيضاء). */
    public static function labelForKey(?string $key): string
    {
        return self::labelFor($key === null ? null : self::tryFrom($key));
    }
}
