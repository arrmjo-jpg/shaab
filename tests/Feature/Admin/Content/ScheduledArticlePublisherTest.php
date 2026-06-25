<?php

declare(strict_types=1);

use App\Actions\Admin\Content\PublishDueArticlesAction;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\Category;
use App\Models\User;
use App\Support\Scheduler\SchedulerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function pdArticle(string $status, ?string $publishedAt): Article
{
    $cat = Category::create([
        'name' => 'تصنيف '.uniqid(), 'locale' => 'ar', 'status' => 'active', 'scope' => 'both',
    ]);

    return Article::create([
        'author_id' => User::factory()->create()->id,
        'primary_category_id' => $cat->id,
        'type' => 'news',
        'status' => $status,
        'locale' => 'ar',
        'title' => 'عنوان '.uniqid(),
        'slug' => 'slug-'.uniqid(),
        'content_json' => tiptapDoc(),
        'published_at' => $publishedAt,
    ]);
}

it('publishes a due scheduled article with system actor', function (): void {
    $a = pdArticle('scheduled', now()->subMinute()->toDateTimeString());

    $count = app(PublishDueArticlesAction::class)->handle();

    expect($count)->toBe(1);
    $fresh = $a->fresh();
    expect($fresh->status)->toBe(ArticleStatus::Published);
    expect($fresh->published_by_id)->toBeNull(); // system actor
    expect($fresh->published_at)->not->toBeNull();
});

it('writes a revision and audit on automated publish', function (): void {
    $a = pdArticle('scheduled', now()->subMinute()->toDateTimeString());

    app(PublishDueArticlesAction::class)->handle();

    expect(ArticleRevision::where('article_id', $a->id)->count())->toBe(1);
    expect(Activity::where('log_name', 'article')->exists())->toBeTrue();
});

it('does not publish a future scheduled article', function (): void {
    $a = pdArticle('scheduled', now()->addDay()->toDateTimeString());

    $count = app(PublishDueArticlesAction::class)->handle();

    expect($count)->toBe(0);
    expect($a->fresh()->status)->toBe(ArticleStatus::Scheduled);
});

it('ignores non-scheduled articles', function (): void {
    $draft = pdArticle('draft', now()->subDay()->toDateTimeString());
    $published = pdArticle('published', now()->subDay()->toDateTimeString());

    $count = app(PublishDueArticlesAction::class)->handle();

    expect($count)->toBe(0);
    expect($draft->fresh()->status)->toBe(ArticleStatus::Draft);
    expect($published->fresh()->status)->toBe(ArticleStatus::Published);
});

it('is idempotent across repeated runs', function (): void {
    pdArticle('scheduled', now()->subMinute()->toDateTimeString());

    expect(app(PublishDueArticlesAction::class)->handle())->toBe(1);
    expect(app(PublishDueArticlesAction::class)->handle())->toBe(0);
    expect(ArticleRevision::count())->toBe(1);
});

it('runs via the artisan command', function (): void {
    pdArticle('scheduled', now()->subMinute()->toDateTimeString());

    $this->artisan('articles:publish-due')->assertExitCode(0);

    expect(Article::where('status', 'published')->count())->toBe(1);
});

it('is registered in the scheduler registry as managed/critical', function (): void {
    $def = SchedulerRegistry::find('articles_publish_due');

    expect($def)->not->toBeNull();
    expect($def['command'])->toBe('articles:publish-due');
    expect($def['cron'])->toBe('* * * * *');
    expect($def['critical'])->toBeTrue();
});
