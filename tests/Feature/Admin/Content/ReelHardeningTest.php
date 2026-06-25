<?php

declare(strict_types=1);

use App\Actions\Admin\Content\DeleteReelAction;
use App\Actions\Admin\Content\PublishDueReelsAction;
use App\Actions\Admin\Content\ReelStatsAction;
use App\Actions\Admin\Media\DeleteMediaAssetAction;
use App\Actions\Admin\Media\PruneOrphanMediaAssetsAction;
use App\Models\MediaAsset;
use App\Models\Reel;
use App\Modules\CDN\Jobs\ProcessCdnPurgeBatch;
use App\Settings\CdnSettings;
use App\Support\Cache\ReelCacheTags;
use App\Support\Content\ReelCdnPurge;
use App\Support\Media\TranscodeProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
});

function rhEnableCdnAutoPurge(): void
{
    $s = app(CdnSettings::class);
    $s->cdn_enabled = true;
    $s->cdn_auto_purge = true;
    $s->cdn_plan = 'free';
    $s->cdn_api_token = 'test-token';
    $s->cdn_zone_id = 'test-zone';
    $s->save();
}

function rhReadyAsset(?int $ageDays = null): MediaAsset
{
    $asset = MediaAsset::create([
        'uuid' => 'rh-'.uniqid(),
        'disk' => 'public',
        'path' => 'assets/'.uniqid().'/source.mp4',
        'filename' => 'source.mp4',
        'original_name' => 'source.mp4',
        'extension' => 'mp4',
        'size' => 2048,
        'mime_type' => 'video/mp4',
        'processing_status' => 'ready',
        'visibility' => 'public',
    ]);
    if ($ageDays !== null) {
        $asset->forceFill(['created_at' => now()->subDays($ageDays)])->save();
    }

    return $asset;
}

function rhReel(array $attrs = []): Reel
{
    return Reel::create(array_merge([
        'title' => 'ريل '.uniqid(),
        'locale' => 'ar',
        'status' => 'draft',
    ], $attrs));
}

// ─── Scheduled-publish automation ───────────────────────────────────────────

it('auto-publishes a due scheduled reel with ready media', function (): void {
    $reel = rhReel([
        'status' => 'scheduled',
        'published_at' => now()->subMinute(),
        'media_asset_id' => rhReadyAsset()->id,
    ]);

    $count = (new PublishDueReelsAction)->handle();

    expect($count)->toBe(1);
    $fresh = Reel::find($reel->id);
    expect($fresh->status->value)->toBe('published');
    expect($fresh->published_by_id)->toBeNull(); // فاعل النظام
});

it('does not publish a reel scheduled for the future', function (): void {
    $reel = rhReel([
        'status' => 'scheduled',
        'published_at' => now()->addDay(),
        'media_asset_id' => rhReadyAsset()->id,
    ]);

    expect((new PublishDueReelsAction)->handle())->toBe(0);
    expect(Reel::find($reel->id)->status->value)->toBe('scheduled');
});

it('skips a due scheduled reel whose media is not ready (stays scheduled)', function (): void {
    $asset = rhReadyAsset();
    $asset->forceFill(['processing_status' => 'processing'])->save();
    $reel = rhReel([
        'status' => 'scheduled',
        'published_at' => now()->subMinute(),
        'media_asset_id' => $asset->id,
    ]);

    expect((new PublishDueReelsAction)->handle())->toBe(0);
    expect(Reel::find($reel->id)->status->value)->toBe('scheduled');
});

// ─── Media governance: reel-linked assets are never orphans ─────────────────

it('orphan-prune never deletes a reel-linked asset', function (): void {
    $linked = rhReadyAsset(ageDays: 3);   // أقدم من TTL لكنه مرتبط بريل
    rhReel(['media_asset_id' => $linked->id]);

    $unlinked = rhReadyAsset(ageDays: 3); // أقدم من TTL وغير مرتبط

    $pruned = (new PruneOrphanMediaAssetsAction)->handle();

    expect($pruned)->toBe(1);
    expect(MediaAsset::find($linked->id))->not->toBeNull();   // محميّ
    expect(MediaAsset::find($unlinked->id))->toBeNull();      // مُنظَّف
});

it('delete guard counts reel usage (blocks delete without force)', function (): void {
    $asset = rhReadyAsset();
    rhReel(['media_asset_id' => $asset->id]);

    $result = (new DeleteMediaAssetAction)->handle($asset, false);

    expect($result['deleted'])->toBeFalse();
    expect($result['usage_count'])->toBe(1);
    expect(MediaAsset::find($asset->id))->not->toBeNull();
});

// ─── Dashboard stat-card counts ─────────────────────────────────────────────

it('reel stats action counts by status and trashed', function (): void {
    rhReel(['status' => 'draft']);
    rhReel(['status' => 'published', 'published_at' => now()->subDay()]);
    rhReel(['status' => 'published', 'published_at' => now()->subDay()]);
    rhReel(['status' => 'archived']);
    rhReel(['status' => 'draft'])->delete(); // trashed

    $data = (new ReelStatsAction)->handle()->getData(true)['data'];

    expect($data['draft'])->toBe(1);
    expect($data['published'])->toBe(2);
    expect($data['archived'])->toBe(1);
    expect($data['scheduled'])->toBe(0);
    expect($data['total'])->toBe(4);   // غير المحذوفة
    expect($data['trashed'])->toBe(1);
});

// ─── CDN edge invalidation on reel content writes ───────────────────────────

it('dispatches a CDN purge batch when auto-publishing with auto-purge enabled', function (): void {
    rhEnableCdnAutoPurge();
    Queue::fake();

    rhReel([
        'status' => 'scheduled',
        'published_at' => now()->subMinute(),
        'media_asset_id' => rhReadyAsset()->id,
    ]);

    expect((new PublishDueReelsAction)->handle())->toBe(1);

    Queue::assertPushed(ProcessCdnPurgeBatch::class);
});

it('does not touch the CDN when auto-purge is disabled (default)', function (): void {
    Queue::fake();

    rhReel([
        'status' => 'scheduled',
        'published_at' => now()->subMinute(),
        'media_asset_id' => rhReadyAsset()->id,
    ]);

    expect((new PublishDueReelsAction)->handle())->toBe(1);

    Queue::assertNotPushed(ProcessCdnPurgeBatch::class);
});

it('purge URL set covers feeds + api endpoints for the reel locale', function (): void {
    rhEnableCdnAutoPurge();
    Queue::fake(); // امنع تنفيذ الـ job كي يبقى الـ buffer للفحص

    $reel = rhReel(['locale' => 'ar', 'media_asset_id' => rhReadyAsset()->id]);
    ReelCdnPurge::purge($reel);

    $urls = Cache::get('cdn:purge:buffer', []);

    expect(collect($urls)->contains(fn (string $u): bool => str_contains($u, '/ar/reels/featured')))->toBeTrue();
    expect(collect($urls)->contains(fn (string $u): bool => str_contains($u, '/ar/reels/trending')))->toBeTrue();
    expect(collect($urls)->contains(fn (string $u): bool => str_contains($u, 'api/v1/ar/reels')))->toBeTrue();
    expect(collect($urls)->contains(fn (string $u): bool => str_contains($u, 'api/v1/ar/reels/featured')))->toBeTrue();
    expect(collect($urls)->contains(fn (string $u): bool => str_contains($u, 'api/v1/ar/reels/trending')))->toBeTrue();
});

// ─── Granular cache invalidation (locale isolation) ─────────────────────────

it('granular invalidation flushes only the mutated locale feed', function (): void {
    Cache::tags(ReelCacheTags::feedTags('ar'))->put('ar_feed', 1, 300);
    Cache::tags(ReelCacheTags::feedTags('en'))->put('en_feed', 1, 300);

    $reel = rhReel(['locale' => 'ar']);
    (new DeleteReelAction)->handle($reel);

    // لغة الريل (ar) أُبطِلت؛ اللغة الأخرى (en) سليمة — لا تفريغ شامل.
    expect(Cache::tags(ReelCacheTags::feedTags('ar'))->get('ar_feed'))->toBeNull();
    expect(Cache::tags(ReelCacheTags::feedTags('en'))->get('en_feed'))->toBe(1);
});

it('detail invalidation isolates a single reel slug', function (): void {
    Cache::tags(ReelCacheTags::detailTags('ar', 'reel-a'))->put('a', 1, 300);
    Cache::tags(ReelCacheTags::detailTags('ar', 'reel-b'))->put('b', 1, 300);

    $reel = rhReel(['locale' => 'ar', 'slug' => 'reel-a']);
    (new DeleteReelAction)->handle($reel);

    // تفاصيل reel-a أُبطِلت؛ تفاصيل reel-b سليمة (عزل حقيقي على مستوى الريل).
    expect(Cache::tags(ReelCacheTags::detailTags('ar', 'reel-a'))->get('a'))->toBeNull();
    expect(Cache::tags(ReelCacheTags::detailTags('ar', 'reel-b'))->get('b'))->toBe(1);
});

// ─── Transcoding progress checklist (granular operational visibility) ───────

function rhVideoAsset(string $status, array $conversions = [], ?int $height = 1280): MediaAsset
{
    return MediaAsset::create([
        'uuid' => 'tp-'.uniqid(),
        'disk' => 'public',
        'path' => 'assets/'.uniqid().'/source.mp4',
        'filename' => 'source.mp4',
        'original_name' => 'source.mp4',
        'extension' => 'mp4',
        'size' => 2048,
        'mime_type' => 'video/mp4',
        'processing_status' => $status,
        'processing_profile' => 'reel',
        'visibility' => 'public',
        'height' => $height,
        'width' => 720,
        'conversions' => $conversions,
    ]);
}

it('reports granular partial progress while processing', function (): void {
    $asset = rhVideoAsset('processing', ['poster' => ['path' => 'p.jpg']]);

    $p = TranscodeProgress::for($asset);

    expect($p['status'])->toBe('processing');
    // source + metadata (height set) + poster جاهزة؛ البقية pending.
    expect($p['completed'])->toBe(3);
    expect($p['failed_stage'])->toBeNull();
    expect(collect($p['artifacts'])->firstWhere('key', 'hls_master')['state'])->toBe('pending');
});

it('derives the failure stage from persisted partial artifacts', function (): void {
    // فشل بعد الـ poster وقبل الـ HLS ⇒ المرحلة hls_master.
    $asset = rhVideoAsset('failed', ['poster' => ['path' => 'p.jpg']]);

    $p = TranscodeProgress::for($asset);

    expect($p['failed_stage'])->toBe('hls_master');
    expect(collect($p['artifacts'])->firstWhere('key', 'poster')['state'])->toBe('ready');
    expect(collect($p['artifacts'])->firstWhere('key', 'hls_master')['state'])->toBe('failed');
});

it('marks best-effort artifacts skipped on a ready low-res asset', function (): void {
    // مصدر 720p: لا تُنتَج دقّة 1080p (بلا upscaling)، وWebP غائبة ⇒ متخطّاة.
    $asset = rhVideoAsset('ready', [
        'poster' => ['path' => 'p.jpg'],
        'hls' => ['master' => 'hls/master.m3u8', 'variants' => ['360p' => 'x']],
        'renditions' => ['variants' => ['360p' => 'a', '480p' => 'b', '720p' => 'c']],
    ], height: 720);

    $p = TranscodeProgress::for($asset);

    // لا يوجد عنصر mp4_1080 أصلاً (غير متوقّع لمصدر 720p).
    expect(collect($p['artifacts'])->pluck('key'))->not->toContain('mp4_1080');
    expect(collect($p['artifacts'])->firstWhere('key', 'thumbnail_webp')['state'])->toBe('skipped');
    expect(collect($p['artifacts'])->firstWhere('key', 'mp4_720')['state'])->toBe('ready');
});
