<?php

declare(strict_types=1);

use App\Actions\Admin\VideoLibrary\DeleteVideoAction;
use App\Actions\Admin\VideoLibrary\DeleteVideoPlaylistAction;
use App\Models\Video;
use App\Models\VideoCategory;
use App\Models\VideoPlaylist;
use App\Support\Frontend\FrontendCacheTags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const REVALIDATE_URL = 'https://frontend.test/api/revalidate';

function configureFrontendRevalidate(): void
{
    config([
        'services.frontend_revalidate.url' => REVALIDATE_URL,
        'services.frontend_revalidate.secret' => 'test-secret',
        'services.frontend_revalidate.timeout' => 5,
    ]);
}

// ─── Tag translation (backend VideoCacheTags → Next tag taxonomy) ────────────────

it('translates backend video cache tags to the frontend taxonomy and drops umbrellas', function (): void {
    $tags = FrontendCacheTags::fromVideoTags([
        'videos',                     // ALL umbrella → no frontend tag
        'videos:sitemap',             // sitemap → no frontend tag
        'videos:feed:ar',
        'videos:detail:ar:my-clip',
        'videos:category:ar:sports',
        'videos:playlist:ar:mix',
    ]);

    expect($tags)->toHaveCount(4)
        ->toContain('video-feed:ar')
        ->toContain('video:ar:my-clip')
        ->toContain('video-category:ar:sports')
        ->toContain('playlist:ar:mix');
});

// ─── Video write → authenticated revalidation with correct tags ──────────────────

it('notifies the Next frontend with video + category tags on a video write', function (): void {
    configureFrontendRevalidate();
    Http::fake([REVALIDATE_URL => Http::response(['ok' => true, 'revalidated' => []], 200)]);

    $category = VideoCategory::factory()->create(['locale' => 'ar']);
    $video = Video::factory()->published()->create(['locale' => 'ar', 'video_category_id' => $category->id]);

    (new DeleteVideoAction)->handle($video);

    Http::assertSent(function ($request) use ($video, $category): bool {
        $tags = $request->data()['tags'] ?? [];

        return $request->url() === REVALIDATE_URL
            && $request->hasHeader('x-revalidate-secret', 'test-secret')
            && in_array('video-feed:ar', $tags, true)
            && in_array("video:ar:{$video->slug}", $tags, true)
            && in_array("video-category:ar:{$category->slug}", $tags, true);
    });
});

// ─── Playlist write → revalidation with playlist + feed tags ─────────────────────

it('notifies the Next frontend with playlist + feed tags on a playlist write', function (): void {
    configureFrontendRevalidate();
    Http::fake([REVALIDATE_URL => Http::response(['ok' => true], 200)]);

    $playlist = VideoPlaylist::factory()->published()->create(['locale' => 'ar']);

    (new DeleteVideoPlaylistAction)->handle($playlist);

    Http::assertSent(function ($request) use ($playlist): bool {
        $tags = $request->data()['tags'] ?? [];

        return in_array('video-feed:ar', $tags, true)
            && in_array("playlist:ar:{$playlist->slug}", $tags, true);
    });
});

// ─── Safe no-op when the webhook is unconfigured (fail-closed gate) ──────────────

it('makes zero calls when frontend revalidation is unconfigured', function (): void {
    config(['services.frontend_revalidate.url' => '', 'services.frontend_revalidate.secret' => '']);
    Http::fake();

    $video = Video::factory()->published()->create(['locale' => 'ar']);
    (new DeleteVideoAction)->handle($video);

    Http::assertNothingSent();
});
