<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Settings\ThirdPartySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * تدفّق الدخول الاجتماعيّ (OAuth عبر Socialite، stateless للـ API).
 * الاعتمادات تُضبط وقت التشغيل من ThirdPartySettings (لوحة الإدارة). الربط بالبريد الموثّق من
 * المزوّد (لا حاجة لأعمدة provider). عند النجاح يُصدَر توكن Sanctum ويُحوَّل المستخدم إلى الواجهة.
 */
class SocialAuthController extends Controller
{
    public function redirect(string $provider): RedirectResponse
    {
        $settings = app(ThirdPartySettings::class);
        abort_unless($this->isEnabled($provider, $settings), 404);

        $this->configure($provider, $settings);

        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function callback(string $provider, Request $request): RedirectResponse
    {
        $settings = app(ThirdPartySettings::class);

        if (! $this->isEnabled($provider, $settings)) {
            return redirect($this->frontend('/login?social=disabled'));
        }

        $this->configure($provider, $settings);

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Throwable) {
            return redirect($this->frontend('/login?social=failed'));
        }

        $email = $socialUser->getEmail();
        if (! $email) {
            return redirect($this->frontend('/login?social=no_email'));
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $user = User::create([
                'name' => $socialUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Str::random(48),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]);
            $user->assignRole('user');
        }

        if (in_array($user->status, [UserStatus::Suspended, UserStatus::Banned], true)) {
            return redirect($this->frontend('/login?social=blocked'));
        }

        $user->recordLogin((string) $request->ip());
        $token = $user->createToken('public-token', ['user'])->plainTextToken;

        return redirect($this->frontend('/api/auth/social/finish?token='.urlencode($token)));
    }

    private function isEnabled(string $provider, ThirdPartySettings $settings): bool
    {
        return match ($provider) {
            'google' => (bool) $settings->google_enabled,
            'facebook' => (bool) $settings->facebook_enabled,
            default => false,
        };
    }

    private function configure(string $provider, ThirdPartySettings $settings): void
    {
        $config = match ($provider) {
            'google' => [
                'client_id' => $settings->google_client_id,
                'client_secret' => $settings->google_client_secret,
                'redirect' => $settings->google_redirect_url ?: url("/api/v1/auth/social/{$provider}/callback"),
            ],
            'facebook' => [
                'client_id' => $settings->facebook_client_id,
                'client_secret' => $settings->facebook_client_secret,
                'redirect' => $settings->facebook_redirect_url ?: url("/api/v1/auth/social/{$provider}/callback"),
            ],
            default => [],
        };

        config(["services.{$provider}" => $config]);
    }

    private function frontend(string $path): string
    {
        return rtrim((string) config('frontend.public_url'), '/').$path;
    }
}
