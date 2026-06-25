<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function wfEditor(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

function wfWriter(): array
{
    $role = Role::findByName('contributor', 'web');
    $role->givePermissionTo(['articles.view', 'articles.edit']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create(['is_writer' => true]);
    $u->assignRole('contributor');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

function wfArticle(int $authorId, string $status = 'draft'): Article
{
    $cat = Category::create([
        'name' => 'تصنيف '.uniqid(), 'locale' => 'ar', 'status' => 'active', 'scope' => 'both',
    ]);

    return Article::create([
        'author_id' => $authorId,
        'primary_category_id' => $cat->id,
        'type' => 'news',
        'status' => $status,
        'locale' => 'ar',
        'title' => 'عنوان '.uniqid(),
        'slug' => 'slug-'.uniqid(),
        'content_json' => tiptapDoc(),
    ]);
}

// ─── Writer transitions ────────────────────────────────────────────────

it('writer submits own draft for review', function (): void {
    [$writer, $token] = wfWriter();
    $a = wfArticle($writer->id, 'draft');

    $this->withToken($token)->patchJson("/api/v1/admin/articles/{$a->id}/status", [
        'status' => 'submitted',
    ])->assertOk()->assertJsonPath('data.status', 'submitted');
});

it('writer can resubmit a rejected article', function (): void {
    [$writer, $token] = wfWriter();
    $a = wfArticle($writer->id, 'rejected');

    $this->withToken($token)->patchJson("/api/v1/admin/articles/{$a->id}/status", [
        'status' => 'submitted',
    ])->assertOk();
});

it('writer cannot publish', function (): void {
    [$writer, $token] = wfWriter();
    $a = wfArticle($writer->id, 'draft');

    $this->withToken($token)->patchJson("/api/v1/admin/articles/{$a->id}/status", [
        'status' => 'published',
    ])->assertStatus(403);
    expect($a->fresh()->status)->toBe(ArticleStatus::Draft);
});

it('writer cannot transition another writers article', function (): void {
    [, $token] = wfWriter();
    $other = wfArticle(User::factory()->create()->id, 'draft');

    $this->withToken($token)->patchJson("/api/v1/admin/articles/{$other->id}/status", [
        'status' => 'submitted',
    ])->assertStatus(403);
});

// ─── Editorial transitions ─────────────────────────────────────────────

it('editorial moves submitted → in_review → published with stamps', function (): void {
    [$editor, $token] = wfEditor();
    $a = wfArticle(User::factory()->create()->id, 'submitted');

    $this->withToken($token)->patchJson("/api/v1/admin/articles/{$a->id}/status", ['status' => 'in_review'])
        ->assertOk();
    $this->withToken($token)->patchJson("/api/v1/admin/articles/{$a->id}/status", ['status' => 'published'])
        ->assertOk()->assertJsonPath('data.status', 'published');

    $fresh = $a->fresh();
    expect($fresh->published_at)->not->toBeNull();
    expect($fresh->published_by_id)->toBe($editor->id);
});

it('scheduling requires a future published_at', function (): void {
    [, $token] = wfEditor();
    $a = wfArticle(User::factory()->create()->id, 'draft');

    $this->withToken($token)->patchJson("/api/v1/admin/articles/{$a->id}/status", ['status' => 'scheduled'])
        ->assertStatus(422);
    $this->withToken($token)->patchJson("/api/v1/admin/articles/{$a->id}/status", [
        'status' => 'scheduled', 'published_at' => now()->subDay()->toISOString(),
    ])->assertStatus(422);

    $this->withToken($token)->patchJson("/api/v1/admin/articles/{$a->id}/status", [
        'status' => 'scheduled', 'published_at' => now()->addDay()->toISOString(),
    ])->assertOk()->assertJsonPath('data.status', 'scheduled');
    expect($a->fresh()->published_at)->not->toBeNull();
});

it('editorial rejects a submitted article', function (): void {
    [, $token] = wfEditor();
    $a = wfArticle(User::factory()->create()->id, 'submitted');

    $this->withToken($token)->patchJson("/api/v1/admin/articles/{$a->id}/status", ['status' => 'rejected'])
        ->assertOk()->assertJsonPath('data.status', 'rejected');
});

it('editorial archives a published article', function (): void {
    [, $token] = wfEditor();
    $a = wfArticle(User::factory()->create()->id, 'published');

    $this->withToken($token)->patchJson("/api/v1/admin/articles/{$a->id}/status", ['status' => 'archived'])
        ->assertOk()->assertJsonPath('data.status', 'archived');
});

it('rejects an illegal transition', function (): void {
    [, $token] = wfEditor();
    $a = wfArticle(User::factory()->create()->id, 'published');

    $this->withToken($token)->patchJson("/api/v1/admin/articles/{$a->id}/status", ['status' => 'submitted'])
        ->assertStatus(422);
});

// ─── Side effects: revision + audit ────────────────────────────────────

it('writes a revision and audits on transition', function (): void {
    [$editor, $token] = wfEditor();
    $a = wfArticle(User::factory()->create()->id, 'submitted');

    $this->withToken($token)->patchJson("/api/v1/admin/articles/{$a->id}/status", ['status' => 'published'])
        ->assertOk();

    expect(ArticleRevision::where('article_id', $a->id)->count())->toBe(1);
    expect(Activity::where('log_name', 'article')->exists())->toBeTrue();
});

// ─── Authorization ─────────────────────────────────────────────────────

it('denies transition without a token', function (): void {
    $a = wfArticle(User::factory()->create()->id, 'draft');
    $this->patchJson("/api/v1/admin/articles/{$a->id}/status", ['status' => 'submitted'])
        ->assertStatus(401);
});

it('denies transition without the edit permission', function (): void {
    $role = Role::findByName('reviewer', 'web');
    $role->givePermissionTo('articles.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create();
    $u->assignRole('reviewer');
    $token = $u->createToken('admin-token', ['admin'])->plainTextToken;
    $a = wfArticle(User::factory()->create()->id, 'draft');

    $this->withToken($token)->patchJson("/api/v1/admin/articles/{$a->id}/status", [
        'status' => 'submitted',
    ])->assertStatus(403);
});
