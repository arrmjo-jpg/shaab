<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CDN module messages (Cloudflare)
|--------------------------------------------------------------------------
*/

return [
    'settings_updated' => 'CDN settings were updated successfully.',
    'test_success' => 'The Cloudflare connection was verified successfully.',
    'test_failed' => 'Failed to verify the Cloudflare connection. Check the token.',
    'token_missing' => 'The Cloudflare token has not been set yet.',
    'disabled' => 'The CDN service is not enabled or its setup is incomplete.',
    'purge_done' => 'The cache was purged successfully.',
    'purge_queued' => 'The cache purge was queued successfully.',
    'purge_all_done' => 'The entire cache was purged successfully.',
    'purge_failed' => 'Failed to purge the cache. Try again later.',
];
