<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Auth — English. Full parity with the ar locale (no fallback leakage).
|--------------------------------------------------------------------------
*/

return [

    // ─── Laravel defaults ───────────────────────────────────────────
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    // ─── Expected business outcomes ─────────────────────────────────
    'suspended' => 'Your account is temporarily suspended. Please contact support.',
    'banned' => 'Your account has been permanently banned.',
    'account_inactive' => 'Your account is suspended or banned. Please contact support.',
    'email_unverified' => 'You must verify your email before accessing the admin panel.',
    'recaptcha_failed' => 'reCAPTCHA verification failed. Please try again.',

    // ─── Success messages ───────────────────────────────────────────
    'register_success' => 'Account created successfully.',
    'login_success' => 'Signed in successfully.',
    'logout_success' => 'Signed out successfully.',
    'admin_login_success' => 'Welcome to the admin panel.',
    'forgot_password_sent' => 'A password reset link has been sent to your email.',
    'reset_password_success' => 'Your password has been changed. You can sign in now.',

    'reset_email' => [
        'subject' => 'Reset your password',
        'greeting' => 'Hello',
        'line1' => 'You are receiving this email because we received a password reset request for your account.',
        'action' => 'Reset password',
        'expire' => 'This link will expire in :count minutes.',
        'line2' => 'If you did not request a password reset, no further action is required.',
        'origin' => 'Password reset was requested from :source (IP: :ip).',
        'salutation' => 'Regards, :app team',
    ],

    'reset_source' => [
        'admin_web' => 'Admin Web Panel',
        'admin_flutter' => 'Admin Mobile App',
        'public_web' => 'Public Website',
        'public_mobile' => 'Mobile App',
        'api_direct' => 'Direct API',
        'unknown' => 'unknown source',
    ],

    'activity' => [
        'password_reset_requested' => 'Password reset requested',
        'admin_login' => 'Signed in to the admin panel',
        'profile_updated' => 'Profile updated',
        'password_changed' => 'Password changed',
        'sessions_revoked_others' => 'All other sessions revoked',
    ],

    'verify_email' => [
        'subject' => 'Confirm your email',
        'greeting' => 'Hello',
        'line1' => 'Your email must be verified to access the admin panel. Click the button below to confirm.',
        'action' => 'Verify email',
        'expire' => 'This link will expire in :count minutes.',
        'line2' => 'If you did not request this, no action is required.',
        'sent' => 'If the account is eligible, a verification link has been sent to your email.',
    ],
];
