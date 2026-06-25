<?php

declare(strict_types=1);

use App\Rules\ResolvableBroadcastSourceUrl;
use App\Support\Broadcast\BroadcastSourceValidator;
use Illuminate\Support\Facades\Validator;

// ─── Trusted-allowlist + HTTPS-only + SSRF guard (BroadcastSourceValidator) ──

it('accepts a trusted YouTube Live https url for youtube_live', function (): void {
    expect(BroadcastSourceValidator::isAllowed('youtube_live', 'https://www.youtube.com/watch?v=abc123'))->toBeTrue();
    expect(BroadcastSourceValidator::isAllowed('youtube_live', 'https://youtu.be/abc123'))->toBeTrue();
});

it('rejects untrusted hosts, http, obfuscated/private IPs, and unknown types', function (string $type, string $url): void {
    expect(BroadcastSourceValidator::isAllowed($type, $url))->toBeFalse();
})->with([
    'http (no tls)' => ['youtube_live', 'http://www.youtube.com/watch?v=x'],
    'untrusted host' => ['youtube_live', 'https://evil.example/live'],
    'decimal ip (127.0.0.1)' => ['hls', 'https://2130706433/live.m3u8'],
    'private ip' => ['hls', 'https://10.0.0.5/live.m3u8'],
    'ipv6 ula' => ['hls', 'https://[fd00::1]/live.m3u8'],
    'empty url' => ['hls', ''],
    'unknown source type' => ['rtmp', 'https://www.youtube.com/watch?v=x'],
]);

it('rejects every host when a source type has an empty allowlist (safe default)', function (): void {
    config(['broadcast.allowed_hosts.hls' => []]);
    expect(BroadcastSourceValidator::isAllowed('hls', 'https://cdn.example.com/live.m3u8'))->toBeFalse();
});

it('accepts an operator-allowlisted hls host (subdomain, https-only) and isolates per type', function (): void {
    config(['broadcast.allowed_hosts.hls' => ['example.com']]);

    expect(BroadcastSourceValidator::isAllowed('hls', 'https://cdn.example.com/live.m3u8'))->toBeTrue();
    expect(BroadcastSourceValidator::isAllowed('hls', 'http://cdn.example.com/live.m3u8'))->toBeFalse(); // https-only
    expect(BroadcastSourceValidator::isAllowed('hls', 'https://notexample.com/live.m3u8'))->toBeFalse();
    // per-type isolation: a youtube host is NOT valid under the hls allowlist
    expect(BroadcastSourceValidator::isAllowed('hls', 'https://www.youtube.com/watch?v=x'))->toBeFalse();
});

// ─── Validation rule wiring (DataAwareRule reads sibling source_type) ────────

it('passes the rule for a trusted source and fails for an untrusted one', function (): void {
    $ok = Validator::make(
        ['source_type' => 'youtube_live', 'source_url' => 'https://www.youtube.com/watch?v=x'],
        ['source_url' => [new ResolvableBroadcastSourceUrl]],
    );
    expect($ok->passes())->toBeTrue();

    $bad = Validator::make(
        ['source_type' => 'youtube_live', 'source_url' => 'https://evil.example/live'],
        ['source_url' => [new ResolvableBroadcastSourceUrl]],
    );
    expect($bad->fails())->toBeTrue();
});
