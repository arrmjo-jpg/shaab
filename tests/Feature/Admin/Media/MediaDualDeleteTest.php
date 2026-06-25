<?php

declare(strict_types=1);

use App\Actions\Admin\Media\DeleteMediaAssetAction;
use App\Actions\Admin\Media\PruneOrphanMediaAssetsAction;
use App\Models\MediaAsset;
use App\Support\Media\MediaFileCleaner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function ddAsset(array $attrs = []): MediaAsset
{
    $uuid = $attrs['uuid'] ?? 'dd-'.uniqid();

    return MediaAsset::create(array_merge([
        'uuid' => $uuid,
        'disk' => 'uploads',
        'path' => "assets/{$uuid}/{$uuid}.mp4",
        'filename' => "{$uuid}.mp4",
        'original_name' => 'reel.mp4',
        'extension' => 'mp4',
        'size' => 10,
        'mime_type' => 'video/mp4',
        'visibility' => 'public',
        'stored_local' => true,
        'stored_remote' => true,
    ], $attrs));
}

it('deletes both local canonical and remote mirror copies', function (): void {
    Storage::fake('uploads');
    Storage::fake('media_remote');
    $a = ddAsset();
    $prefix = dirname($a->path);
    Storage::disk('uploads')->put($a->path, 'x');
    Storage::disk('uploads')->put("{$prefix}/poster.jpg", 'p');
    Storage::disk('media_remote')->put($a->path, 'x');
    Storage::disk('media_remote')->put("{$prefix}/poster.jpg", 'p');

    MediaFileCleaner::purge($a);

    Storage::disk('uploads')->assertMissing($a->path);
    Storage::disk('uploads')->assertMissing("{$prefix}/poster.jpg");
    Storage::disk('media_remote')->assertMissing($a->path);
    Storage::disk('media_remote')->assertMissing("{$prefix}/poster.jpg");
});

it('deletes the remote copy for legacy remote-only assets (no local error)', function (): void {
    Storage::fake('s3');
    $a = ddAsset(['disk' => 's3', 'stored_local' => false, 'stored_remote' => true]);
    Storage::disk('s3')->put($a->path, 'x');

    MediaFileCleaner::purge($a); // must not throw

    Storage::disk('s3')->assertMissing($a->path);
});

it('fail-safe: local is still deleted when remote delete fails', function (): void {
    Storage::fake('uploads'); // media_remote intentionally unconfigured → resolving throws
    $a = ddAsset();
    Storage::disk('uploads')->put($a->path, 'x');

    MediaFileCleaner::purge($a); // must not throw

    Storage::disk('uploads')->assertMissing($a->path); // canonical removed despite remote failure
});

it('explicit delete action removes both copies and the row', function (): void {
    Storage::fake('uploads');
    Storage::fake('media_remote');
    $a = ddAsset();
    Storage::disk('uploads')->put($a->path, 'x');
    Storage::disk('media_remote')->put($a->path, 'x');

    $result = (new DeleteMediaAssetAction)->handle($a, false);

    expect($result['deleted'])->toBeTrue();
    Storage::disk('uploads')->assertMissing($a->path);
    Storage::disk('media_remote')->assertMissing($a->path);
    expect(MediaAsset::find($a->id))->toBeNull();
});

it('orphan prune dual-deletes a remote-only orphan', function (): void {
    Storage::fake('s3');
    $a = ddAsset(['disk' => 's3', 'stored_local' => false, 'stored_remote' => true]);
    Storage::disk('s3')->put($a->path, 'x');
    // older than TTL + unattached ⇒ prunable
    $a->forceFill(['created_at' => now()->subDays(3)])->save();

    $pruned = (new PruneOrphanMediaAssetsAction)->handle();

    expect($pruned)->toBe(1);
    Storage::disk('s3')->assertMissing($a->path);
    expect(MediaAsset::find($a->id))->toBeNull();
});
