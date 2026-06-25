<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // FFmpeg/FFprobe — مسارات ثنائيّات اختيارية؛ تُستخدَم لتحويل الفيديو المرفوع
    // (HLS + نسخ MP4 + الصور المصغّرة). فارغة ⇒ يُعتمَد على PATH النظام
    // (ffmpeg / ffprobe). على ويندوز/التطوير حيث لا يرث مسار عملية الطابور PATH
    // التفاعلي، يلزم ضبط FFMPEG_PATH / FFPROBE_PATH صراحةً في .env.
    'ffmpeg' => [
        'ffmpeg_path' => env('FFMPEG_PATH'),
        'ffprobe_path' => env('FFPROBE_PATH'),
    ],

    // Next public frontend cache revalidation (Phase 11). On content publish/update the backend
    // POSTs the affected cache tags to the Next /api/revalidate endpoint (x-revalidate-secret).
    // Unset url/secret ⇒ safe no-op (zero calls). Tags mirror the Next cache-tag taxonomy.
    'frontend_revalidate' => [
        'url' => env('FRONTEND_REVALIDATE_URL'),
        'secret' => env('FRONTEND_REVALIDATE_SECRET'),
        'timeout' => (int) env('FRONTEND_REVALIDATE_TIMEOUT', 5),
    ],

    // توكن نداءات الخادم-لخادم (SSR Next ⇒ Laravel). يحمله Next على قراءاته (X-Internal-Token) فيتجاوز
    // حارس throttle:public.read — الأصل الموثوق ليس عميلاً عامّاً مُسيئاً. فارغ ⇒ لا تجاوز (آمن افتراضاً).
    'internal_api' => [
        'token' => env('INTERNAL_API_TOKEN'),
    ],

    // Legacy search-engine sitemap ping (Google + Bing) on publish. Default OFF — opt-in via
    // env. NOTE: both endpoints are effectively deprecated (Google's 404s); fire-and-forget so
    // it never affects publishing. Real discovery is the sitemap in robots.txt + Search Console.
    'search_ping' => [
        'enabled' => (bool) env('SEARCH_PING_ENABLED', false),
    ],

    // Social login (Socialite). Credentials are normally driven at runtime from ThirdPartySettings
    // (admin-configured); these env defaults are the fallback structure Socialite expects to exist.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

];
