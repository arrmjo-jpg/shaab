<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function mcToken(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin-token', ['admin'])->plainTextToken;
}

function mcWriter(): User
{
    $u = User::factory()->create(['is_writer' => true]);
    $u->assignRole('journalist');

    return $u;
}

function mcCat(string $scope, ?int $parentId = null): Category
{
    return Category::create([
        'name' => 'تصنيف '.uniqid(),
        'locale' => 'ar',
        'status' => 'active',
        'scope' => $scope,
        'parent_id' => $parentId,
    ]);
}

/** @param array<string,mixed> $overrides */
function mcArticlePayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'عنوان الخبر',
        'locale' => 'ar',
        'type' => 'news',
        'excerpt' => 'ملخّص.',
        'content_json' => tiptapDoc(),
    ], $overrides);
}

// ─── Contract: the API speaks `both`, never `mixed` ──────────────────────────

it('persists a mixed category created via the API as scope=both', function (): void {
    $token = mcToken();

    $res = $this->withToken($token)->postJson('/api/v1/admin/categories', [
        'name' => 'تصنيف مختلط', 'locale' => 'ar', 'status' => 'active', 'scope' => 'both',
    ])->assertCreated();

    expect($res->json('data.scope'))->toBe('both');
});

it('rejects the legacy `mixed` scope value (frontend/backend contract guard)', function (): void {
    $token = mcToken();

    $this->withToken($token)->postJson('/api/v1/admin/categories', [
        'name' => 'تصنيف', 'locale' => 'ar', 'status' => 'active', 'scope' => 'mixed',
    ])->assertStatus(422)->assertJsonValidationErrors('scope');
});

it('exposes scope=both for mixed categories in the list the editor selector reads', function (): void {
    $token = mcToken();
    mcCat('both');

    $res = $this->withToken($token)->getJson('/api/v1/admin/categories')->assertOk();
    $scopes = collect($res->json('data'))->pluck('scope');
    expect($scopes)->toContain('both');
});

// ─── A mixed (both) category is attachable across content types ──────────────

it('attaches a mixed category as primary on a NEWS article', function (): void {
    $token = mcToken();
    $cat = mcCat('both');

    $this->withToken($token)
        ->postJson('/api/v1/admin/articles', mcArticlePayload(['primary_category_id' => $cat->id]))
        ->assertCreated();
});

it('attaches a mixed category as primary on an OPINION article', function (): void {
    $token = mcToken();
    $cat = mcCat('both');
    $writer = mcWriter();

    $this->withToken($token)->postJson('/api/v1/admin/articles', mcArticlePayload([
        'type' => 'opinion',
        'primary_category_id' => $cat->id,
        'author_id' => $writer->id,
    ]))->assertCreated();
});

it('attaches a mixed category as a SECONDARY category on a news article', function (): void {
    $token = mcToken();
    $primary = mcCat('news');
    $secondary = mcCat('both');

    $this->withToken($token)->postJson('/api/v1/admin/articles', mcArticlePayload([
        'primary_category_id' => $primary->id,
        'secondary_category_ids' => [$secondary->id],
    ]))->assertCreated();
});

// ─── No regression: scope rules still enforced for non-mixed categories ──────

it('rejects an opinion-only category on a news article', function (): void {
    $token = mcToken();
    $cat = mcCat('opinion');

    $this->withToken($token)
        ->postJson('/api/v1/admin/articles', mcArticlePayload(['primary_category_id' => $cat->id]))
        ->assertStatus(422);
});

it('rejects a news-only category on an opinion article', function (): void {
    $token = mcToken();
    $cat = mcCat('news');
    $writer = mcWriter();

    $this->withToken($token)->postJson('/api/v1/admin/articles', mcArticlePayload([
        'type' => 'opinion',
        'primary_category_id' => $cat->id,
        'author_id' => $writer->id,
    ]))->assertStatus(422);
});
