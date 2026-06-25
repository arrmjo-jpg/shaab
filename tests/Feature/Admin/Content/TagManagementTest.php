<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Tags\Tag;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function tgmSuperToken(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

function tgmCategory(): Category
{
    return Category::create([
        'name' => 'c-'.uniqid(),
        'slug' => 'cat-'.uniqid(),
        'locale' => 'ar',
        'scope' => 'both',
        'status' => 'active',
    ]);
}

function tgmArticle(): Article
{
    return Article::create([
        'primary_category_id' => tgmCategory()->id,
        'type' => 'news',
        'status' => 'published',
        'locale' => 'ar',
        'title' => 'مقال '.uniqid(),
        'slug' => 'a-'.Str::random(8),
        'published_at' => now()->subDay(),
    ]);
}

function tgmTag(string $ar): Tag
{
    return Tag::findOrCreate($ar, null, 'ar');
}

// ─── List + usage count ───────────────────────────────────────────────────

it('lists tags with real usage counts (most-used first)', function (): void {
    $token = tgmSuperToken();
    $a1 = tgmArticle();
    $a2 = tgmArticle();
    $pol = tgmTag('سياسة');
    $eco = tgmTag('اقتصاد');
    $a1->attachTags([$pol, $eco]);
    $a2->attachTags([$pol]);

    $res = $this->withToken($token)->getJson('/api/v1/admin/tags/manage?locale=ar')->assertOk();

    expect($res->json('data'))->toHaveCount(2);
    expect($res->json('data.0.usage_count'))->toBe(2);      // سياسة (الأكثر استخداماً)
    expect($res->json('data.0.name.ar'))->toBe('سياسة');
    expect($res->json('data.1.usage_count'))->toBe(1);      // اقتصاد
    expect($res->json('meta.pagination.total'))->toBe(2);
});

// ─── Rename ─────────────────────────────────────────────────────────────────

it('renames a tag (name per locale + regenerates latin slug)', function (): void {
    $token = tgmSuperToken();
    $eco = tgmTag('اقتصاد');

    $this->withToken($token)
        ->putJson("/api/v1/admin/tags/{$eco->id}", ['name' => ['ar' => 'مال وأعمال', 'en' => 'Business']])
        ->assertOk();

    $fresh = Tag::find($eco->id);
    expect($fresh->getTranslation('name', 'ar'))->toBe('مال وأعمال');
    expect($fresh->getTranslation('name', 'en'))->toBe('Business');
    expect($fresh->getTranslation('slug', 'en'))->toBe('business');
});

it('rejects rename when no name is provided (422)', function (): void {
    $token = tgmSuperToken();
    $t = tgmTag('سياسة');

    $this->withToken($token)
        ->putJson("/api/v1/admin/tags/{$t->id}", ['name' => []])
        ->assertStatus(422);
});

// ─── Delete (detaches from content) ──────────────────────────────────────────

it('deletes a tag and detaches it from all content', function (): void {
    $token = tgmSuperToken();
    $a1 = tgmArticle();
    $pol = tgmTag('سياسة');
    $a1->attachTags([$pol]);
    expect(DB::table('taggables')->where('tag_id', $pol->id)->count())->toBe(1);

    $this->withToken($token)->deleteJson("/api/v1/admin/tags/{$pol->id}")->assertOk();

    expect(Tag::find($pol->id))->toBeNull();
    expect(DB::table('taggables')->where('tag_id', $pol->id)->count())->toBe(0);
});

// ─── Permissions ─────────────────────────────────────────────────────────────

it('requires tags permissions for manage/rename/delete (403 without role)', function (): void {
    $token = User::factory()->create()->createToken('admin', ['admin'])->plainTextToken; // no roles
    $t = tgmTag('سياسة');

    $this->withToken($token)->getJson('/api/v1/admin/tags/manage')->assertStatus(403);
    $this->withToken($token)->putJson("/api/v1/admin/tags/{$t->id}", ['name' => ['ar' => 'س']])->assertStatus(403);
    $this->withToken($token)->deleteJson("/api/v1/admin/tags/{$t->id}")->assertStatus(403);
});
