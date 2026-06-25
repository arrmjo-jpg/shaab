<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

/** كاتب: مستخدم بـ ability=user + is_writer=true (اسم فريد — مستقلّ عن بقيّة الملفّات). */
function catWriterToken(): array
{
    $u = User::factory()->create(['is_writer' => true]);
    $u->assignRole('user');

    return [$u, $u->createToken('public', ['user'])->plainTextToken];
}

function makeScopedCategory(string $scope): Category
{
    return Category::create([
        'name' => 'c-'.uniqid(),
        'slug' => 'cat-'.uniqid(),
        'locale' => 'ar',
        'scope' => $scope,
        'status' => 'active',
    ]);
}

// ─── 1. خبر ⇒ أقسام scope∈{news,both} (يُستبعَد opinion) ───────────────────
it('returns news + both categories for the news type (excludes opinion)', function (): void {
    [, $token] = catWriterToken();
    $news = makeScopedCategory('news');
    $both = makeScopedCategory('both');
    $opinion = makeScopedCategory('opinion');

    $res = $this->withToken($token)->getJson('/api/v1/article-categories?type=news');

    $res->assertOk();
    $ids = collect($res->json('data'))->pluck('id')->all();
    expect($ids)->toContain($news->id);
    expect($ids)->toContain($both->id);
    expect($ids)->not->toContain($opinion->id);
});

// ─── 2. مقال ⇒ أقسام scope∈{opinion,both} (يُستبعَد news) ──────────────────
it('returns opinion + both categories for the opinion type (excludes news)', function (): void {
    [, $token] = catWriterToken();
    $news = makeScopedCategory('news');
    $both = makeScopedCategory('both');
    $opinion = makeScopedCategory('opinion');

    $res = $this->withToken($token)->getJson('/api/v1/article-categories?type=opinion');

    $res->assertOk();
    $ids = collect($res->json('data'))->pluck('id')->all();
    expect($ids)->toContain($opinion->id);
    expect($ids)->toContain($both->id);
    expect($ids)->not->toContain($news->id);
});

// ─── 3. غير الكاتب → 403 ──────────────────────────────────────────────────
it('forbids a non-writer from reading writer form categories', function (): void {
    $u = User::factory()->create(['is_writer' => false]);
    $u->assignRole('user');
    $token = $u->createToken('public', ['user'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/article-categories?type=news')->assertStatus(403);
});

// ─── 4. نوع غير مسموح (live) → 422 ────────────────────────────────────────
it('rejects an invalid/disallowed article type', function (): void {
    [, $token] = catWriterToken();

    $this->withToken($token)->getJson('/api/v1/article-categories?type=live')->assertStatus(422);
});
