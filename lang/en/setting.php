<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Settings module messages
|--------------------------------------------------------------------------
*/

return [
    'group_not_found' => 'The requested settings group was not found.',

    'updated' => 'The settings were updated successfully.',
    'branding_uploaded' => 'The branding files were uploaded successfully.',

    'firebase_uploaded' => 'The Firebase credentials file was uploaded successfully.',
    'firebase_invalid_json' => 'The Firebase credentials file is invalid or is missing the project ID.',

    'mail_test_success' => 'The test message was sent successfully. The mail settings are correct.',
    'mail_test_failed' => 'Failed to connect to the mail server. Check the settings.',
    'mail_test_subject' => 'Test message from AlphaCMS',
    'mail_test_body' => 'This is a test message to confirm that the mail server settings are correct.',

    'cdn_test_success' => 'The Cloudflare connection was verified successfully.',
    'cdn_test_failed' => 'Failed to verify the Cloudflare connection. Check the token.',
    'cdn_token_missing' => 'The Cloudflare token has not been set yet.',

    'integration_key_missing' => 'The API key has not been set yet.',
    'sportmonks_test_success' => 'The SportMonks connection was verified successfully.',
    'sportmonks_test_failed' => 'Failed to verify the SportMonks connection. Check the key.',
    'openweather_test_success' => 'The OpenWeather connection was verified successfully.',
    'openweather_test_failed' => 'Failed to verify the OpenWeather connection. Check the key.',
    'whatsapp_test_success' => 'The WhatsApp (UltraMsg) connection was verified successfully.',
    'whatsapp_test_failed' => 'Failed to verify the WhatsApp connection. Check the instance and token.',

    'media_test_success' => 'The remote storage connection was verified successfully.',
    'media_test_failed' => 'Failed to connect to the remote storage. Check the credentials.',
    'media_test_missing' => 'Credentials are incomplete (key/secret/bucket are required).',
    'media_remote_disabled' => 'Remote storage is not enabled. Enable it first before syncing.',
    'media_sync_started' => 'Syncing of unsynced media has started in the background.',
    'media_endpoint_unsafe' => 'The endpoint must be an https URL on a public host (no internal/private hosts).',
];
