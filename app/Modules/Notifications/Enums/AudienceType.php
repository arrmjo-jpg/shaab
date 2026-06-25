<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/**
 * نوع جمهور أوّل-الدرجة. كلّ قيمة لها resolver يُحلّها لمستلمين حسب القناة.
 * writer_followers مؤجّل (لا متابعة كتّاب في البيانات بعد). custom يستند لقواعد segment.
 */
enum AudienceType: string
{
    case All = 'all';
    case Logged = 'logged';
    case Guests = 'guests';
    case SportsFollowers = 'sports_followers';
    case TeamFollowers = 'team_followers';
    case WriterFollowers = 'writer_followers';
    case WhatsappSubscribers = 'whatsapp_subscribers';
    case EmailSubscribers = 'email_subscribers';
    case Android = 'android';
    case Ios = 'ios';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::All => 'كلّ المستخدمين',
            self::Logged => 'المسجّلون',
            self::Guests => 'الضيوف',
            self::SportsFollowers => 'متابعو الرياضة',
            self::TeamFollowers => 'متابعو فريق',
            self::WriterFollowers => 'متابعو كاتب',
            self::WhatsappSubscribers => 'مشتركو واتساب',
            self::EmailSubscribers => 'مشتركو البريد',
            self::Android => 'أجهزة Android',
            self::Ios => 'أجهزة iOS',
            self::Custom => 'مخصّص',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $t): string => $t->value, self::cases());
    }
}
