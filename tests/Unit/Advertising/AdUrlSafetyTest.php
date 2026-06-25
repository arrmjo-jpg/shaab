<?php

declare(strict_types=1);

use App\Support\Advertising\AdUrlSafety;

it('accepts http/https with a host and rejects everything else', function (): void {
    expect(AdUrlSafety::isSafe('https://example.com/landing'))->toBeTrue()
        ->and(AdUrlSafety::isSafe('http://a.b'))->toBeTrue()
        ->and(AdUrlSafety::isSafe('javascript:alert(1)'))->toBeFalse()
        ->and(AdUrlSafety::isSafe('data:text/html,<x>'))->toBeFalse()
        ->and(AdUrlSafety::isSafe('ftp://host/x'))->toBeFalse()
        ->and(AdUrlSafety::isSafe('/relative/path'))->toBeFalse()
        ->and(AdUrlSafety::isSafe('https://'))->toBeFalse() // no host
        ->and(AdUrlSafety::isSafe(''))->toBeFalse()
        ->and(AdUrlSafety::isSafe(null))->toBeFalse();
});

it('safeTarget returns the safe url or null (no open redirect)', function (): void {
    expect(AdUrlSafety::safeTarget('https://x.y/z'))->toBe('https://x.y/z')
        ->and(AdUrlSafety::safeTarget('javascript:x'))->toBeNull();
});
