<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

/** كاتب: مستخدم بـ ability=user + is_writer=true. */
function myArticlesWriterToken(): array
{
    $u = User::factory()->create(['is_writer' => true]);
    $u->assignRole('user');

    return [$u, $u->createToken('public', ['user'])->plainTextToken];
}

function myArticlesCategory(): Category
{
    return Category::create([
        'name' => 'c-'.uniqid(),
        'slug' => 'cat-'.uniqid(),
        'locale' => 'ar',
        'scope' => 'news',
        'status' => 'active',
    ]);
}

function makeArticle(int $authorId, int $categoryId, string $status): Article
{
    return Article::create([
        'author_id' => $authorId,
        'primary_category_id' => $categoryId,
        'type' => 'news',
        'status' => $status,
        'locale' => 'ar',
        'title' => 'مقال '.$status.'-'.uniqid(),
        'content_json' => tiptapDoc(),
        'content' => '<p>محتوى</p>',
    ]);
}

// ─── 1. الكاتب يرى مقالاته بكل الحالات + status ظاهر ──────────────────────
it('lists the writer own articles across all statuses with status visible', function (): void {
    [$writer, $token] = myArticlesWriterToken();
    $cat = myArticlesCategory();
    makeArticle($writer->id, $cat->id, 'draft');
    makeArticle($writer->id, $cat->id, 'submitted');
    makeArticle($writer->id, $cat->id, 'published');

    $response = $this->withToken($token)->getJson('/api/v1/articles/mine');

    $response->assertOk();
    assertSuccessContract($response);
    expect($response->json('meta.pagination.total'))->toBe(3);

    // status حاضر في العقد (جوهر C1: الكاتب يرى الحالة).
    $statuses = collect($response->json('data'))->pluck('status')->sort()->values()->all();
    expect($statuses)->toBe(['draft', 'published', 'submitted']);
});

// ─── 2. الكاتب لا يرى مقالات غيره ─────────────────────────────────────────
it('never returns articles owned by another author', function (): void {
    [$writer, $token] = myArticlesWriterToken();
    $other = User::factory()->create(['is_writer' => true]);
    $cat = myArticlesCategory();

    $mine = makeArticle($writer->id, $cat->id, 'draft');
    makeArticle($other->id, $cat->id, 'draft');
    makeArticle($other->id, $cat->id, 'published');

    $response = $this->withToken($token)->getJson('/api/v1/articles/mine');

    $response->assertOk();
    expect($response->json('meta.pagination.total'))->toBe(1);
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$mine->id]);
});

// ─── 3. غير الكاتب → 403 ──────────────────────────────────────────────────
it('returns 403 for a non-writer user', function (): void {
    $u = User::factory()->create(['is_writer' => false]);
    $u->assignRole('user');
    $token = $u->createToken('public', ['user'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/articles/mine')->assertStatus(403);
});

// ─── 4. غير المُصادَق → 401 ───────────────────────────────────────────────
it('returns 401 without a token', function (): void {
    $this->getJson('/api/v1/articles/mine')->assertStatus(401);
});

// ─── 5. فلترة بالحالة (status) تعمل ───────────────────────────────────────
it('filters the writer articles by status', function (): void {
    [$writer, $token] = myArticlesWriterToken();
    $cat = myArticlesCategory();
    makeArticle($writer->id, $cat->id, 'draft');
    makeArticle($writer->id, $cat->id, 'submitted');

    $response = $this->withToken($token)->getJson('/api/v1/articles/mine?filter[status]=submitted');

    $response->assertOk();
    expect($response->json('meta.pagination.total'))->toBe(1);
    expect($response->json('data.0.status'))->toBe('submitted');
});
