<?php

declare(strict_types=1);

use App\Models\MediaAsset;
use App\Models\User;
use App\Support\WpMigration\WpMediaImporter;
use App\Support\WpMigration\WpMediaResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function pngBytes(): string
{
    return (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
    );
}

function up(string $name): string
{
    return 'https://shaab.test/wp-content/uploads/'.$name;
}

/** @param array<int,string> $srcs */
function imageDoc(array $srcs): array
{
    return [
        'type' => 'doc',
        'content' => array_map(fn (string $s): array => ['type' => 'image', 'attrs' => ['src' => $s]], $srcs),
    ];
}

beforeEach(function (): void {
    Storage::fake('uploads');
    Queue::fake(); // لا تُشغّل وظيفة توليد المشتقّات
    config(['media-library.disk_name' => 'uploads']);

    $this->root = sys_get_temp_dir().'/wpmig-up-'.uniqid();
    @mkdir($this->root, 0777, true);
    file_put_contents($this->root.'/a.png', pngBytes());
    file_put_contents($this->root.'/b.png', pngBytes()."\x00"); // مختلف عن a (checksum)
    file_put_contents($this->root.'/text.jpg', 'this is plain text, not an image');

    $this->importer = new WpMediaImporter(new WpMediaResolver($this->root), User::factory()->create());
});

afterEach(function (): void {
    if (! isset($this->root) || ! is_dir($this->root)) {
        return;
    }
    foreach ((array) glob($this->root.'/*') as $f) {
        @unlink($f);
    }
    @rmdir($this->root);
});

it('imports a local image and dedups globally by checksum (#1)', function (): void {
    $first = $this->importer->import(up('a.png'));
    $second = $this->importer->import(up('a.png'));

    expect($first['asset'])->not->toBeNull();
    expect($second['asset']->id)->toBe($first['asset']->id); // same checksum → reused
    expect(MediaAsset::query()->count())->toBe(1);
});

it('imports a repeated src once and rewrites every occurrence (#2)', function (): void {
    $res = $this->importer->rewriteDoc(imageDoc([up('a.png'), up('a.png')]));

    expect($res->imported)->toBe(1);   // imported once (implies rewrite succeeded)
    expect($res->reused)->toBe(1);     // second occurrence reused
    expect(MediaAsset::query()->count())->toBe(1);
    expect($res->doc['content'][0]['attrs']['src'])->toBe($res->doc['content'][1]['attrs']['src']);
});

it('rejects oversized files deterministically and retains the original (#4)', function (): void {
    config(['wp-migration.media.max_bytes' => 10]);

    $res = $this->importer->rewriteDoc(imageDoc([up('a.png')]));

    expect($res->imported)->toBe(0);
    expect($res->doc['content'][0]['attrs']['src'])->toBe(up('a.png')); // retained
    expect(collect($res->warnings)->pluck('reason'))->toContain('media_too_large');
});

it('validates MIME by content, not extension, and retains the original (#5)', function (): void {
    $res = $this->importer->rewriteDoc(imageDoc([up('text.jpg')]));

    expect($res->imported)->toBe(0);
    expect($res->doc['content'][0]['attrs']['src'])->toBe(up('text.jpg'));
    expect(collect($res->warnings)->pluck('reason'))->toContain('media_unsupported_mime');
});

it('bounds per-post fetching with a deterministic cap (#3)', function (): void {
    config(['wp-migration.media.per_post_max' => 1]);

    $res = $this->importer->rewriteDoc(imageDoc([up('a.png'), up('b.png')]));

    expect($res->imported)->toBe(1);
    expect($res->doc['content'][1]['attrs']['src'])->toBe(up('b.png')); // capped → retained
    expect(collect($res->warnings)->pluck('reason'))->toContain('media_capped');
});

it('blocks SSRF targets for external media (#3)', function (): void {
    $res = $this->importer->import('https://127.0.0.1/x.png');

    expect($res['asset'])->toBeNull();
    expect($res['reason'])->toBe('media_ssrf_blocked');
});

it('fetches a public external image over https and imports it', function (): void {
    Http::fake(['cdn.example.test/*' => Http::response(pngBytes(), 200, ['Content-Type' => 'image/png'])]);

    $res = $this->importer->import('https://cdn.example.test/x.png');

    expect($res['asset'])->not->toBeNull();
    expect($res['reason'])->toBeNull();
});
