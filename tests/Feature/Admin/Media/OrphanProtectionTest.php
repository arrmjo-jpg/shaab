<?php

declare(strict_types=1);

use App\Actions\Admin\Media\PruneOrphanMediaAssetsAction;
use App\Models\MediaAsset;
use App\Models\Reel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
});

/** أصل مكتبة أقدم من TTL الافتراضي (48س) فيكون مؤهَّلاً للتنظيف. */
function opAsset(string $status = 'ready', int $ageDays = 3): MediaAsset
{
    $asset = MediaAsset::create([
        'uuid' => 'op-'.uniqid(),
        'disk' => 'public',
        'path' => 'assets/'.uniqid().'/source.mp4',
        'filename' => 'source.mp4',
        'original_name' => 'source.mp4',
        'extension' => 'mp4',
        'size' => 2048,
        'mime_type' => 'video/mp4',
        'processing_status' => $status,
        'visibility' => 'public',
    ]);
    // created_at قديم (التحديث لا يلمس created_at)
    $asset->forceFill(['created_at' => now()->subDays($ageDays)])->save();

    return $asset;
}

function opReel(int $assetId): Reel
{
    return Reel::create([
        'title' => 'ريل '.uniqid(),
        'locale' => 'ar',
        'status' => 'draft',
        'media_asset_id' => $assetId,
    ]);
}

// 1) المحذوف ناعماً يحمي وسائطه
it('soft-deleted reel still protects its attached media', function (): void {
    $asset = opAsset();
    $reel = opReel($asset->id);

    $reel->delete(); // soft delete

    $pruned = (new PruneOrphanMediaAssetsAction)->handle();

    expect($pruned)->toBe(0);
    expect(MediaAsset::find($asset->id))->not->toBeNull();
});

// 2) الحذف النهائي يسمح بالتنظيف
it('force-deleted reel allows its media to be pruned', function (): void {
    $asset = opAsset();
    $reel = opReel($asset->id);

    $reel->forceDelete(); // permanent — يُفصل الأصل فعلياً

    $pruned = (new PruneOrphanMediaAssetsAction)->handle();

    expect($pruned)->toBe(1);
    expect(MediaAsset::find($asset->id))->toBeNull();
});

// 3) الوسائط غير المُسنَدة تُنظَّف
it('truly detached media is pruned', function (): void {
    $asset = opAsset(); // لا ريل مرتبط

    $pruned = (new PruneOrphanMediaAssetsAction)->handle();

    expect($pruned)->toBe(1);
    expect(MediaAsset::find($asset->id))->toBeNull();
});

// 4) أصل فاشل لكنه مُسنَد ⇒ يبقى محميّاً
it('a failed but attached asset survives pruning', function (): void {
    $asset = opAsset(status: 'failed');
    opReel($asset->id); // مرتبط بريل حيّ

    $pruned = (new PruneOrphanMediaAssetsAction)->handle();

    expect($pruned)->toBe(0);
    expect(MediaAsset::find($asset->id))->not->toBeNull();
});

// 5) أصل فاشل وغير مُسنَد ⇒ يُنظَّف
it('a failed unattached asset is pruned normally', function (): void {
    $asset = opAsset(status: 'failed'); // لا ريل/مقال

    $pruned = (new PruneOrphanMediaAssetsAction)->handle();

    expect($pruned)->toBe(1);
    expect(MediaAsset::find($asset->id))->toBeNull();
});

// إضافي: المحذوف ناعماً ثم المحذوف نهائياً ⇒ يصبح مؤهَّلاً
it('media becomes prunable only after the protecting reel is force-deleted', function (): void {
    $asset = opAsset();
    $reel = opReel($asset->id);

    $reel->delete();
    expect((new PruneOrphanMediaAssetsAction)->handle())->toBe(0); // محميّ في السلّة

    Reel::withTrashed()->find($reel->id)->forceDelete();
    expect((new PruneOrphanMediaAssetsAction)->handle())->toBe(1); // الآن مؤهَّل
    expect(MediaAsset::find($asset->id))->toBeNull();
});
