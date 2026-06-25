<?php

declare(strict_types=1);

use App\Models\EngagementCounter;
use App\Models\MediaAsset;
use App\Models\Video;
use App\Support\Engagement\EngagementActor;
use App\Support\Engagement\EngagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function dedupVideo(): Video
{
    $asset = MediaAsset::create([
        'uuid' => (string) Str::uuid(), 'kind' => 'external', 'disk' => 'external', 'path' => '',
        'filename' => '', 'original_name' => 'x', 'mime_type' => 'video/external', 'extension' => '',
        'size' => 0, 'checksum' => hash('sha256', Str::random()), 'provider' => 'youtube',
        'provider_id' => Str::random(11), 'embed_url' => 'https://www.youtube.com/embed/'.Str::random(11),
        'source_url' => 'https://youtu.be/'.Str::random(11), 'poster_url' => 'https://img.youtube.com/x.jpg',
        'visibility' => 'public',
    ]);

    return Video::create([
        'title' => 'فيديو '.uniqid(), 'locale' => 'ar', 'status' => 'published', 'visibility' => 'public',
        'published_at' => now()->subMinute(), 'media_asset_id' => $asset->id, 'source_type' => 'youtube',
    ]);
}

function dedupViews(int $id): int
{
    return (int) (EngagementCounter::query()
        ->where('engageable_type', (new Video)->getMorphClass())
        ->where('engageable_id', $id)
        ->value('views') ?? 0);
}

function dedupKey(int $id, EngagementActor $actor): string
{
    return 'engview:'.Video::class.':'.$id.':'.$actor->key();
}

// ─── Atomic dedup primitive (regression guard) ────────────────────────────────

it('uses atomic Cache::add for view dedup, not check-then-act (has + put)', function (): void {
    $video = dedupVideo();

    // المراقبة بعد إنشاء الهدف كي لا نعترض كاش غير ذي صلة.
    Cache::spy();

    app(EngagementService::class)->recordViewFor(Video::class, $video->id, EngagementActor::guest('spy'));

    // العقد: مطالبة ذرّية واحدة عبر add — بلا فحص-ثم-كتابة (has/put) المعرّض للسباق.
    Cache::shouldHaveReceived('add')->once();
    Cache::shouldNotHaveReceived('has');
    Cache::shouldNotHaveReceived('put');
});

// ─── Lost-race semantics (a competing request claimed the key first) ──────────

it('does not count when the dedup key was already atomically claimed', function (): void {
    $video = dedupVideo();
    $actor = EngagementActor::guest('racer');

    // محاكاة طلب متزامن آخر فاز بالمطالبة الذرّية أولاً.
    Cache::add(dedupKey($video->id, $actor), true, now()->addMinutes(30));

    app(EngagementService::class)->recordViewFor(Video::class, $video->id, $actor);

    expect(dedupViews($video->id))->toBe(0);
});

it('only the first of two competing claims for the same key succeeds', function (): void {
    $video = dedupVideo();
    $actor = EngagementActor::guest('contender');
    $key = dedupKey($video->id, $actor);
    $ttl = now()->addMinutes(30);

    // مطالبتان متنافستان على نفس المفتاح — واحدة فقط تنجح (دلالة SET NX).
    expect(Cache::add($key, true, $ttl))->toBeTrue();
    expect(Cache::add($key, true, $ttl))->toBeFalse();
});

// ─── External behavior unchanged ──────────────────────────────────────────────

it('still counts exactly once per actor and once per distinct actor', function (): void {
    $video = dedupVideo();
    $svc = app(EngagementService::class);

    $svc->recordViewFor(Video::class, $video->id, EngagementActor::guest('a'));
    $svc->recordViewFor(Video::class, $video->id, EngagementActor::guest('a')); // ضمن النافذة ⇒ مُتجاهَل
    expect(dedupViews($video->id))->toBe(1);

    $svc->recordViewFor(Video::class, $video->id, EngagementActor::guest('b'));
    expect(dedupViews($video->id))->toBe(2);
});
