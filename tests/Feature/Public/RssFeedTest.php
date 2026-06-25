<?php

declare(strict_types=1);

use App\Jobs\PingSearchEnginesJob;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Support\Seo\SearchEngineNotify;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Cache::flush();
});

function rssArticle(array $attrs = []): Article
{
    $cat = Category::create([
        'name' => 'قسم '.uniqid(),
        'locale' => 'ar',
        'status' => 'active',
    ]);

    return Article::create(array_merge([
        'title' => 'مقال '.uniqid(),
        'locale' => 'ar',
        'type' => 'news',
        'status' => 'published',
        'primary_category_id' => $cat->id,
        'author_id' => User::factory()->create()->id,
        'content_json' => tiptapDoc(),
        'content' => '<p>محتوى</p>',
        'excerpt' => 'ملخّص الخبر',
        'published_at' => now()->subHour(),
    ], $attrs))->fresh();
}

// ─── RSS 2.0 news feed ──────────────────────────────────────────────────

it('serves a valid RSS 2.0 news feed (published only, stable guid)', function (): void {
    $a = rssArticle(['title' => 'منشور']);
    rssArticle(['title' => 'مسودّة', 'status' => 'draft', 'published_at' => null]);

    $res = $this->get('/rss/news.xml');

    $res->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('application/rss+xml');
    expect($res->headers->get('Cache-Control'))->toContain('public');

    $body = $res->getContent();
    expect($body)->toStartWith('<?xml');
    expect($body)->toContain('<rss version="2.0"');
    expect($body)->toContain('<channel>');
    expect($body)->toContain('<atom:link');
    expect($body)->toContain('<pubDate>');
    // Published present, draft excluded.
    expect(substr_count($body, '<item>'))->toBe(1);
    // Permanent GUID = absolute canonical URL.
    expect($body)->toContain('<guid isPermaLink="true">');
    expect($body)->toContain($a->canonicalPath());
});

it('caps the news feed at 30 items', function (): void {
    foreach (range(1, 35) as $i) {
        rssArticle(['title' => "خبر {$i}"]);
    }

    $body = $this->get('/rss/news.xml')->getContent();

    expect(substr_count($body, '<item>'))->toBe(30);
});

it('serves the videos and reels feeds', function (): void {
    expect($this->get('/rss/videos.xml')->assertOk()->headers->get('Content-Type'))
        ->toContain('application/rss+xml');
    expect($this->get('/rss/reels.xml')->assertOk()->headers->get('Content-Type'))
        ->toContain('application/rss+xml');
});

// ─── Search-engine ping ─────────────────────────────────────────────────

it('does not dispatch the ping when disabled', function (): void {
    config(['services.search_ping.enabled' => false]);
    Queue::fake();

    SearchEngineNotify::sitemaps();

    Queue::assertNothingPushed();
});

it('dispatches the ping job when enabled', function (): void {
    config(['services.search_ping.enabled' => true]);
    Queue::fake();

    SearchEngineNotify::sitemaps();

    Queue::assertPushed(PingSearchEnginesJob::class);
});

it('pings google and bing with the encoded sitemap url', function (): void {
    Http::fake();

    (new PingSearchEnginesJob('https://example.com/sitemap.xml'))->handle();

    Http::assertSent(fn ($req): bool => str_contains($req->url(), 'https://www.google.com/ping?sitemap=')
        && str_contains($req->url(), urlencode('https://example.com/sitemap.xml')));
    Http::assertSent(fn ($req): bool => str_contains($req->url(), 'https://www.bing.com/ping?sitemap='));
});
