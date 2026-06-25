<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Category;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function cmtSuperToken(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

function cmtArticle(): Article
{
    $cat = Category::create([
        'name' => 'c-'.uniqid(),
        'slug' => 'cat-'.uniqid(),
        'locale' => 'ar',
        'scope' => 'both',
        'status' => 'active',
    ]);

    return Article::create([
        'primary_category_id' => $cat->id,
        'type' => 'news',
        'status' => 'published',
        'locale' => 'ar',
        'title' => 'مقال '.uniqid(),
        'slug' => 'a-'.Str::random(8),
        'published_at' => now()->subDay(),
    ]);
}

function cmtComment(Article $article, array $attrs = []): Comment
{
    return Comment::create(array_merge([
        'commentable_type' => $article->getMorphClass(),
        'commentable_id' => $article->id,
        'user_id' => null,
        'author_name' => 'زائر',
        'author_email' => 'guest@example.com',
        'body' => 'تعليق '.uniqid(),
        'status' => 'pending',
    ], $attrs));
}

// ─── Read (comments.view) ─────────────────────────────────────────────────

it('lists comments for admins with comments.view (paginated, author shape)', function (): void {
    $token = cmtSuperToken();
    $article = cmtArticle();
    cmtComment($article, ['status' => 'approved']);
    cmtComment($article, ['status' => 'pending']);

    $res = $this->withToken($token)->getJson('/api/v1/admin/comments')->assertOk();

    expect($res->json('data'))->toHaveCount(2);
    expect($res->json('meta.pagination.total'))->toBe(2);
    expect($res->json('data.0.body'))->not->toBeNull();
    expect($res->json('data.0.status'))->toBeIn(['approved', 'pending']);
    expect($res->json('data.0.author.is_guest'))->toBeTrue();
});

it('surfaces the registered user name on the author block', function (): void {
    $token = cmtSuperToken();
    $article = cmtArticle();
    $author = User::factory()->create(['name' => 'كاتب التعليق']);
    cmtComment($article, ['user_id' => $author->id, 'author_name' => null]);

    $res = $this->withToken($token)->getJson('/api/v1/admin/comments')->assertOk();

    expect($res->json('data.0.author.is_guest'))->toBeFalse();
    expect($res->json('data.0.author.name'))->toBe('كاتب التعليق');
});

it('filters comments by status', function (): void {
    $token = cmtSuperToken();
    $article = cmtArticle();
    cmtComment($article, ['status' => 'approved']);
    cmtComment($article, ['status' => 'pending']);

    $res = $this->withToken($token)->getJson('/api/v1/admin/comments?status=approved')->assertOk();

    expect($res->json('data'))->toHaveCount(1);
    expect($res->json('data.0.status'))->toBe('approved');
});

it('paginates comments', function (): void {
    $token = cmtSuperToken();
    $article = cmtArticle();
    cmtComment($article);
    cmtComment($article);
    cmtComment($article);

    $res = $this->withToken($token)->getJson('/api/v1/admin/comments?per_page=2')->assertOk();

    expect($res->json('data'))->toHaveCount(2);
    expect($res->json('meta.pagination.total'))->toBe(3);
    expect($res->json('meta.pagination.per_page'))->toBe(2);
    expect($res->json('meta.pagination.total_pages'))->toBe(2);
});

// ─── Permission ───────────────────────────────────────────────────────────

it('returns 403 without comments.view', function (): void {
    $token = User::factory()->create()->createToken('admin', ['admin'])->plainTextToken; // no roles

    $this->withToken($token)->getJson('/api/v1/admin/comments')->assertStatus(403);
});
