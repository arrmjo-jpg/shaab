<?php

declare(strict_types=1);

use App\Support\Advertising\AdBeaconToken;
use App\Support\Advertising\AdBucket;

it('issues and verifies a token bound to placement, zone and bucket', function (): void {
    $bucket = AdBucket::current();
    $token = AdBeaconToken::issue(5, 9, $bucket);

    expect(AdBeaconToken::verify($token, 5, 9, $bucket))->toBeTrue();
});

it('rejects wrong target, tampering and bucket replay', function (): void {
    $bucket = AdBucket::current();
    $token = AdBeaconToken::issue(5, 9, $bucket);

    expect(AdBeaconToken::verify($token, 6, 9, $bucket))->toBeFalse()      // wrong placement
        ->and(AdBeaconToken::verify($token, 5, 8, $bucket))->toBeFalse()   // wrong zone
        ->and(AdBeaconToken::verify($token.'x', 5, 9, $bucket))->toBeFalse() // tampered signature
        ->and(AdBeaconToken::verify($token, 5, 9, $bucket + 2))->toBeFalse() // replayed in a later bucket
        ->and(AdBeaconToken::verify($token, 5, 9, $bucket + 1))->toBeTrue();  // one-bucket render grace
});
