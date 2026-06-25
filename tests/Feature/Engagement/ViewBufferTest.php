<?php

declare(strict_types=1);

use App\Models\EngagementCounter;
use App\Models\MediaAsset;
use App\Models\Video;
use App\Support\Engagement\BotSignature;
use App\Support\Engagement\EngagementActor;
use App\Support\Engagement\EngagementBeaconToken;
use App\Support\Engagement\EngagementService;
use App\Support\Engagement\ViewBuffer;
use App\Support\Scheduler\SchedulerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** فيديو عام قابل للتشغيل (أصل خارجي) — مكتفٍ ذاتياً لهذا الملف. */
function vbVideo(): Video
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
        'title' => 'مشاهدات '.uniqid(), 'locale' => 'ar', 'status' => 'published', 'visibility' => 'public',
        'published_at' => now()->subMinute(), 'media_asset_id' => $asset->id, 'source_type' => 'youtube',
    ]);
}

function vbViews(Video $video): int
{
    return (int) (EngagementCounter::query()
        ->where('engageable_type', (new Video)->getMorphClass())
        ->where('engageable_id', $video->id)
        ->value('views') ?? 0);
}

// ─── Bot signature ──────────────────────────────────────────────────────────

it('detects crawler user-agents but not real clients or empty', function (): void {
    expect(BotSignature::isBot('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'))->toBeTrue();
    expect(BotSignature::isBot('facebookexternalhit/1.1'))->toBeTrue();
    expect(BotSignature::isBot('ClaudeBot/1.0'))->toBeTrue();
    expect(BotSignature::isBot('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari'))->toBeFalse();
    expect(BotSignature::isBot('Symfony'))->toBeFalse();
    expect(BotSignature::isBot(''))->toBeFalse();
    expect(BotSignature::isBot(null))->toBeFalse();
});

it('does not count a bot view via the beacon but counts a real client', function (): void {
    $video = vbVideo();
    $token = EngagementBeaconToken::issue('video', $video->id);

    // زاحف: لا تُحتسَب مشاهدة.
    $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)', 'X-Client-Id' => 'bot'])
        ->postJson("/api/v1/engagement/video/{$video->id}/view", ['token' => $token])->assertOk();
    expect(vbViews($video))->toBe(0);

    // عميل حقيقي: تُحتسَب (المسار المتزامن — التجميع معطّل في الاختبارات).
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh) Safari/605', 'X-Client-Id' => 'human'])
        ->postJson("/api/v1/engagement/video/{$video->id}/view", ['token' => $token])->assertOk();
    expect(vbViews($video))->toBe(1);
});

// ─── Buffered counter ─────────────────────────────────────────────────────────

it('buffers view increments and only persists them on flush (coalesced + deduped)', function (): void {
    config(['performance.view_buffer.enabled' => true]);
    expect(ViewBuffer::supported())->toBeTrue(); // array store يدعم الأقفال

    $video = vbVideo();
    $service = app(EngagementService::class);

    // ثلاثة فاعلين متمايزين ⇒ ثلاث مشاهدات مُجمَّعة (لا UPDATE بعد).
    foreach (['a', 'b', 'c'] as $actor) {
        $service->recordViewFor(Video::class, $video->id, EngagementActor::guest($actor));
    }
    expect(vbViews($video))->toBe(0); // لم تُكتَب بعد — في المخزن المؤقّت فقط

    // تكرار نفس الفاعل ضمن النافذة ⇒ يُتجاهَل (منع تكرار).
    $service->recordViewFor(Video::class, $video->id, EngagementActor::guest('a'));

    $flushed = ViewBuffer::flush();
    expect($flushed)->toBe(1);          // هدف واحد فُرّغ
    expect(vbViews($video))->toBe(3);   // 3 مشاهدات فريدة تراكمت في UPDATE واحد
});

it('flush is a safe no-op when nothing is buffered', function (): void {
    expect(ViewBuffer::flush())->toBe(0);

    $this->artisan('engagement:flush-views')->assertSuccessful();
});

it('falls back to synchronous increment when buffering is disabled', function (): void {
    config(['performance.view_buffer.enabled' => false]);

    $video = vbVideo();
    app(EngagementService::class)->recordViewFor(Video::class, $video->id, EngagementActor::guest('z'));

    // متزامن: يظهر فوراً دون تفريغ.
    expect(vbViews($video))->toBe(1);
});

it('registers the flush task in the scheduler registry', function (): void {
    expect(SchedulerRegistry::exists('engagement_flush_views'))->toBeTrue();
    expect(SchedulerRegistry::find('engagement_flush_views')['command'])
        ->toBe('engagement:flush-views');
});
