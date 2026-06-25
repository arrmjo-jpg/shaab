<?php

declare(strict_types=1);

namespace App\Modules\CDN\Services;

use App\Modules\CDN\Enums\CdnFailureType;
use App\Modules\CDN\Support\CdnRateLimiter;
use App\Modules\CDN\Support\CdnRetry;
use App\Modules\CDN\Support\CdnStats;
use App\Settings\CdnSettings;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * عميل Cloudflare الوحيد (لا interface، لا multi-provider، لا failover).
 * يقرأ التوكن/الـzone من CdnSettings الحالي.
 */
final class CloudflareClient
{
    public function __construct(
        private readonly CdnRateLimiter $rateLimiter = new CdnRateLimiter,
        private readonly CdnStats $stats = new CdnStats,
    ) {}

    public function settings(): CdnSettings
    {
        return app(CdnSettings::class);
    }

    public function configured(): bool
    {
        $s = $this->settings();

        return $s->cdn_api_token !== '' && $s->cdn_zone_id !== '';
    }

    public function enabled(): bool
    {
        return $this->settings()->cdn_enabled && $this->configured();
    }

    /**
     * تحقق خفيف من صلاحية التوكن فقط.
     */
    public function verifyToken(): array
    {
        if ($this->settings()->cdn_api_token === '') {
            return ['ok' => false, 'failure' => CdnFailureType::Authentication];
        }

        $result = CdnRetry::run(fn (): array => $this->call(
            'get',
            '/user/tokens/verify'
        ));

        $this->stats->recordTest($result['ok'] ?? false);

        return $result;
    }

    /**
     * @param  array<int, string>  $urls
     */
    public function purge(array $urls): bool
    {
        if ($urls === []) {
            return true;
        }

        $result = CdnRetry::run(fn (): array => $this->zoneCall(['files' => array_values($urls)]));
        $this->stats->recordPurge(count($urls), $result['ok'] ?? false);

        return $result['ok'] ?? false;
    }

    public function purgeAll(): bool
    {
        $result = CdnRetry::run(fn (): array => $this->zoneCall(['purge_everything' => true]));
        $this->stats->recordPurge(0, $result['ok'] ?? false);

        return $result['ok'] ?? false;
    }

    private function zoneCall(array $payload): array
    {
        return $this->call(
            'post',
            '/zones/'.$this->settings()->cdn_zone_id.'/purge_cache',
            $payload
        );
    }

    private function call(string $method, string $path, array $payload = []): array
    {
        if (! $this->rateLimiter->allow()) {
            return ['ok' => false, 'failure' => CdnFailureType::RateLimited];
        }

        $base = rtrim((string) config('cdn.api.base_url'), '/');
        $timeout = (int) config('cdn.api.timeout', 8);

        try {
            /** @var Response $response */
            $response = Http::withToken($this->settings()->cdn_api_token)
                ->acceptJson()
                ->timeout($timeout)
                ->{$method}($base.$path, $payload);
        } catch (Throwable) {
            return ['ok' => false, 'failure' => CdnFailureType::Network];
        }

        if ($response->successful() && $response->json('success') === true) {
            return ['ok' => true, 'failure' => null, 'body' => $response->json()];
        }

        return ['ok' => false, 'failure' => $this->classify($response)];
    }

    private function classify(Response $response): CdnFailureType
    {
        return match (true) {
            $response->status() === 401, $response->status() === 403 => CdnFailureType::Authentication,
            $response->status() === 429 => CdnFailureType::RateLimited,
            $response->status() >= 500 => CdnFailureType::ServerError,
            $response->status() >= 400 => CdnFailureType::ClientError,
            default => CdnFailureType::Unknown,
        };
    }
}
