<?php

declare(strict_types=1);

use App\Models\Broadcast;
use App\Support\Broadcast\BroadcastProbeResult;
use App\Support\Broadcast\BroadcastSourceProbe;
use App\Support\Security\SafeUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'broadcast.allowed_hosts.hls' => ['allowed.test'],
        'broadcast.allowed_hosts.iptv' => ['allowed.test'],
        'broadcast.allowed_hosts.icecast' => ['radio.test'],
        'broadcast.health.verify_resolved_ip' => false, // عزل: لا DNS حقيقي في الاختبارات
    ]);
});

function bpProbe(string $type, string $url): BroadcastProbeResult
{
    $broadcast = Broadcast::factory()->make(['source_type' => $type, 'source_url' => $url]);

    return (new BroadcastSourceProbe)->probe($broadcast);
}

// ─── HLS / IPTV manifest semantics ───────────────────────────────────────────

it('reports healthy for a valid HLS manifest', function (): void {
    Http::fake(['*' => Http::response("#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-STREAM-INF:BANDWIDTH=1\nv.m3u8", 200)]);

    $r = bpProbe('hls', 'https://cdn.allowed.test/live.m3u8');

    expect($r->probeable)->toBeTrue();
    expect($r->healthy)->toBeTrue();
});

it('fails an HLS source on non-200', function (): void {
    Http::fake(['*' => Http::response('', 404)]);

    $r = bpProbe('hls', 'https://cdn.allowed.test/live.m3u8');

    expect($r->healthy)->toBeFalse();
    expect($r->reason)->toBe('http_404');
});

it('fails an HLS source when the body is not an m3u8 manifest', function (): void {
    Http::fake(['*' => Http::response('<html><body>blocked</body></html>', 200)]);

    expect(bpProbe('hls', 'https://cdn.allowed.test/live.m3u8')->reason)->toBe('not_a_manifest');
});

it('fails an HLS source on an unexpected redirect (no redirect-follow)', function (): void {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'https://elsewhere.test/x'])]);

    expect(bpProbe('hls', 'https://cdn.allowed.test/live.m3u8')->reason)->toBe('unexpected_redirect');
});

it('fails safe on a connection timeout', function (): void {
    Http::fake(function (): void {
        throw new ConnectionException('timed out');
    });

    expect(bpProbe('hls', 'https://cdn.allowed.test/live.m3u8')->reason)->toBe('connection_error');
});

// ─── Icecast / Shoutcast audio reachability ──────────────────────────────────

it('reports healthy for an icecast mount returning audio content-type', function (): void {
    Http::fake(['*' => Http::response('', 200, ['Content-Type' => 'audio/mpeg'])]);

    expect(bpProbe('icecast', 'https://stream.radio.test/live')->healthy)->toBeTrue();
});

it('fails an icecast mount that does not return an audio stream', function (): void {
    Http::fake(['*' => Http::response('<html>', 200, ['Content-Type' => 'text/html'])]);

    expect(bpProbe('icecast', 'https://stream.radio.test/live')->reason)->toBe('not_audio_stream');
});

// ─── Not-probeable (embedded providers) ──────────────────────────────────────

it('marks youtube live as not probeable (no server-side health) without any request', function (): void {
    Http::fake();

    $r = bpProbe('youtube_live', 'https://www.youtube.com/watch?v=abc');

    expect($r->probeable)->toBeFalse();
    expect($r->healthy)->toBeFalse();
    Http::assertNothingSent();
});

// ─── SSRF fail-safe: untrusted host rejected before any request ──────────────

it('fails safe (untrusted_source) for a non-allowlisted host without any request', function (): void {
    Http::fake();

    expect(bpProbe('hls', 'https://evil.test/live.m3u8')->reason)->toBe('untrusted_source');
    Http::assertNothingSent();
});

// ─── DNS-rebinding IP guard logic (literal IPs — no real DNS) ─────────────────

it('rejects private/loopback resolved IPs and accepts public ones', function (): void {
    expect(SafeUrl::hostResolvesToPublicIp('127.0.0.1'))->toBeFalse();
    expect(SafeUrl::hostResolvesToPublicIp('10.0.0.5'))->toBeFalse();
    expect(SafeUrl::hostResolvesToPublicIp('169.254.169.254'))->toBeFalse(); // cloud metadata
    expect(SafeUrl::hostResolvesToPublicIp('100.64.0.1'))->toBeFalse();      // CGNAT
    expect(SafeUrl::hostResolvesToPublicIp('[fd00::1]'))->toBeFalse();        // IPv6 ULA
    expect(SafeUrl::hostResolvesToPublicIp('8.8.8.8'))->toBeTrue();
});
