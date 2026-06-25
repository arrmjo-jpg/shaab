<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Models\Article;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function editorialToken(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

function adminWriterToken(): array
{
    $role = Role::findByName('contributor', 'web');
    $role->givePermissionTo(['articles.view', 'articles.create', 'articles.edit']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create(['is_writer' => true]);
    $u->assignRole('contributor');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

function cat(array $o = []): Category
{
    return Category::create(array_merge([
        'name' => 'تصنيف '.uniqid(), 'locale' => 'ar', 'status' => 'active', 'scope' => 'both',
    ], $o));
}

function payload(Category $c, array $o = []): array
{
    return array_merge([
        'title' => 'عنوان', 'locale' => 'ar', 'type' => 'news',
        'primary_category_id' => $c->id, 'excerpt' => 'ملخّص.', 'content_json' => tiptapDoc(),
    ], $o);
}

// ─── Locked content type model ─────────────────────────────────────────

it('only allows news, opinion, live types', function (): void {
    expect(ArticleType::values())->toBe(['news', 'opinion', 'live']);
    [, $token] = editorialToken();
    $this->withToken($token)->postJson('/api/v1/admin/articles', payload(cat(), ['type' => 'video']))
        ->assertStatus(422)->assertJsonValidationErrors(['type']);
});

it('exposes the locked workflow states including submitted', function (): void {
    expect(ArticleStatus::values())
        ->toBe(['draft', 'submitted', 'in_review', 'scheduled', 'published', 'rejected', 'archived']);
});

// ─── Category scope (C1 amendment) ─────────────────────────────────────

it('defaults category scope to both and exposes it', function (): void {
    [, $token] = editorialToken();
    $res = $this->withToken($token)->postJson('/api/v1/admin/categories', [
        'name' => 'رياضة', 'locale' => 'ar',
    ])->assertCreated();
    expect($res->json('data.scope'))->toBe('both');

    $res2 = $this->withToken($token)->postJson('/api/v1/admin/categories', [
        'name' => 'رأي', 'locale' => 'ar', 'scope' => 'opinion',
    ])->assertCreated();
    expect($res2->json('data.scope'))->toBe('opinion');
});

it('rejects a news article using an opinion-scope category', function (): void {
    [, $token] = editorialToken();
    $opinionCat = cat(['scope' => 'opinion']);

    $this->withToken($token)->postJson('/api/v1/admin/articles', payload($opinionCat, ['type' => 'news']))
        ->assertStatus(422);
});

it('allows an opinion article with multiple categories (unified model)', function (): void {
    [, $token] = editorialToken();
    $writer = User::factory()->create(['is_writer' => true]);
    $p = cat(['scope' => 'opinion']);
    $s = cat(['scope' => 'opinion']);

    $this->withToken($token)->postJson('/api/v1/admin/articles', payload($p, [
        'type' => 'opinion', 'author_id' => $writer->id, 'secondary_category_ids' => [$s->id],
    ]))->assertCreated();
});

// ─── Opinion author attribution ────────────────────────────────────────

it('editorial creating opinion without an author is rejected', function (): void {
    [, $token] = editorialToken();
    $this->withToken($token)->postJson('/api/v1/admin/articles', payload(cat(['scope' => 'opinion']), [
        'type' => 'opinion',
    ]))->assertStatus(422);
});

it('editorial creating opinion with a non-writer author is rejected', function (): void {
    [, $token] = editorialToken();
    $nonWriter = User::factory()->create(['is_writer' => false]);
    $this->withToken($token)->postJson('/api/v1/admin/articles', payload(cat(['scope' => 'opinion']), [
        'type' => 'opinion', 'author_id' => $nonWriter->id,
    ]))->assertStatus(422);
});

it('editorial creating opinion with a writer author binds that author', function (): void {
    [, $token] = editorialToken();
    $writer = User::factory()->create(['is_writer' => true]);
    $res = $this->withToken($token)->postJson('/api/v1/admin/articles', payload(cat(['scope' => 'opinion']), [
        'type' => 'opinion', 'author_id' => $writer->id,
    ]))->assertCreated();

    expect(Article::find($res->json('data.id'))->author_id)->toBe($writer->id);
});

// ─── Writer workflow ───────────────────────────────────────────────────

it('writer self-binds author and creates as draft', function (): void {
    [$writer, $token] = adminWriterToken();
    $res = $this->withToken($token)->postJson('/api/v1/admin/articles', payload(cat(), [
        'type' => 'news', // no author_id — server self-binds the writer
    ]))->assertCreated();

    $a = Article::find($res->json('data.id'));
    expect($a->author_id)->toBe($writer->id);
    expect($a->status)->toBe(ArticleStatus::Draft);
});

it('writer supplying author_id is rejected with 422 (strict — no silent ignore)', function (): void {
    [, $token] = adminWriterToken();
    $someoneElse = User::factory()->create(['is_writer' => true]);

    $this->withToken($token)->postJson('/api/v1/admin/articles', payload(cat(), [
        'type' => 'news', 'author_id' => $someoneElse->id,
    ]))->assertStatus(422);

    expect(Article::count())->toBe(0);
});

it('writer cannot create live coverage', function (): void {
    [, $token] = adminWriterToken();
    $this->withToken($token)->postJson('/api/v1/admin/articles', payload(cat(), ['type' => 'live']))
        ->assertStatus(403);
});

it('writer can edit own draft but not others', function (): void {
    [$writer, $token] = adminWriterToken();
    $id = $this->withToken($token)->postJson('/api/v1/admin/articles', payload(cat()))
        ->json('data.id');

    $this->withToken($token)->putJson("/api/v1/admin/articles/{$id}", ['title' => 'محدّث'])
        ->assertOk();

    $other = Article::create([
        'author_id' => User::factory()->create()->id,
        'primary_category_id' => cat()->id, 'type' => 'news', 'status' => 'draft',
        'locale' => 'ar', 'title' => 'ملك غيري', 'slug' => 'others', 'content_json' => tiptapDoc(),
    ]);
    $this->withToken($token)->putJson("/api/v1/admin/articles/{$other->id}", ['title' => 'سرقة'])
        ->assertStatus(403);
});

it('writer cannot edit own article once out of draft/rejected', function (): void {
    [$writer, $token] = adminWriterToken();
    $id = $this->withToken($token)->postJson('/api/v1/admin/articles', payload(cat()))->json('data.id');
    Article::whereKey($id)->update(['status' => ArticleStatus::Published->value]);

    $this->withToken($token)->putJson("/api/v1/admin/articles/{$id}", ['title' => 'تعديل ممنوع'])
        ->assertStatus(403);
});

it('editorial can edit any article', function (): void {
    [, $wtoken] = adminWriterToken();
    $id = $this->withToken($wtoken)->postJson('/api/v1/admin/articles', payload(cat()))->json('data.id');

    [, $etoken] = editorialToken();
    $this->withToken($etoken)->putJson("/api/v1/admin/articles/{$id}", ['title' => 'تحرير'])
        ->assertOk();
});

// ─── Comments default ──────────────────────────────────────────────────

it('comments are disabled by default on create', function (): void {
    [, $token] = editorialToken();
    $id = $this->withToken($token)->postJson('/api/v1/admin/articles', payload(cat()))->json('data.id');

    expect(Article::find($id)->comments_enabled)->toBeFalse();
});
