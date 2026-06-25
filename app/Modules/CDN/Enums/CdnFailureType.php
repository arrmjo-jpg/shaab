<?php

declare(strict_types=1);

namespace App\Modules\CDN\Enums;

/**
 * تصنيف أعطال نداء Cloudflare API (Cloudflare-only، بلا failover/circuit).
 */
enum CdnFailureType: string
{
    case Authentication = 'authentication';
    case RateLimited = 'rate_limited';
    case Timeout = 'timeout';
    case Network = 'network';
    case ServerError = 'server_error';
    case ClientError = 'client_error';
    case Unknown = 'unknown';

    public function shouldRetry(): bool
    {
        return match ($this) {
            self::RateLimited, self::Timeout, self::Network, self::ServerError => true,
            default => false,
        };
    }
}
