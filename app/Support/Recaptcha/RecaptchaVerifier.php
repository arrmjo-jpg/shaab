<?php

declare(strict_types=1);

namespace App\Support\Recaptcha;

use App\Settings\ThirdPartySettings;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * تحقّق Google reCAPTCHA — يقرأ الإعدادات من ThirdPartySettings.
 * v2: نجاح فقط. v3: نجاح + score + مطابقة action.
 */
final class RecaptchaVerifier
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function settings(): ThirdPartySettings
    {
        return app(ThirdPartySettings::class);
    }

    public function enabled(): bool
    {
        return (bool) $this->settings()->recaptcha_enabled;
    }

    public function verify(?string $token, ?string $ip, string $expectedAction): bool
    {
        $s = $this->settings();

        if ($token === null || $token === '' || $s->recaptcha_secret_key === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(8)
                ->post(self::VERIFY_URL, [
                    'secret' => $s->recaptcha_secret_key,
                    'response' => $token,
                    'remoteip' => $ip,
                ]);
        } catch (Throwable) {
            return false;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            return false;
        }

        // v3: يجب التحقق من النتيجة ومطابقة الـ action
        if ($s->recaptcha_version === 'v3') {
            $score = (float) $response->json('score', 0);
            $action = (string) $response->json('action', '');

            return $score >= (float) $s->recaptcha_score && $action === $expectedAction;
        }

        return true;
    }
}
