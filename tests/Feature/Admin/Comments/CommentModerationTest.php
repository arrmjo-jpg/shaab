<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function cmodSuperToken(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

/** مستخدم بصلاحية واحدة محدّدة فقط (لا super_admin) — لإثبات فصل الصلاحيات. */
function cmodScopedToken(string $permission): string
{
    // دور editor المزروع خالٍ من الصلاحيات افتراضياً (البذور تمنح super_admin فقط)،
    // فمنحه صلاحية واحدة يعزل الفصل: يملك هذه فقط لا غيرها. (نفس نمط اختبارات البثّ.)
    $role = Role::findByName('editor', 'web');
    $role->givePermissionTo($permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create();
    $u->assignRole('editor');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

function cmodArticle(): Article
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

function cmodComment(array $attrs = []): Comment
{
    $article = cmodArticle();

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

// ─── Moderation transitions ─────────────────────────────────────────────────

it('approves a pending comment', function (): void {
    $c = cmodComment();
    $this->withToken(cmodSuperToken())
        ->patchJson("/api/v1/admin/comments/{$c->id}/status", ['status' => 'approved'])
        ->assertOk();
    expect(Comment::find($c->id)->status->value)->toBe('approved');
});

it('rejects a pending comment', function (): void {
    $c = cmodComment();
    $this->withToken(cmodSuperToken())
        ->patchJson("/api/v1/admin/comments/{$c->id}/status", ['status' => 'rejected'])
        ->assertOk();
    expect(Comment::find($c->id)->status->value)->toBe('rejected');
});

it('marks a comment as spam', function (): void {
    $c = cmodComment();
    $this->withToken(cmodSuperToken())
        ->patchJson("/api/v1/admin/comments/{$c->id}/status", ['status' => 'spam'])
        ->assertOk();
    expect(Comment::find($c->id)->status->value)->toBe('spam');
});

it('rejects an invalid moderation status (422)', function (): void {
    $c = cmodComment();
    // pending ليس هدف إشراف صالح
    $this->withToken(cmodSuperToken())
        ->patchJson("/api/v1/admin/comments/{$c->id}/status", ['status' => 'pending'])
        ->assertStatus(422);
});

// ─── Soft delete ────────────────────────────────────────────────────────────

it('soft-deletes a comment', function (): void {
    $c = cmodComment();
    $this->withToken(cmodSuperToken())
        ->deleteJson("/api/v1/admin/comments/{$c->id}")
        ->assertOk();
    expect(Comment::find($c->id))->toBeNull();                       // مُستبعَد من الاستعلام العادي
    expect(Comment::withTrashed()->find($c->id)?->trashed())->toBeTrue(); // موجود محذوفاً ناعماً
});

// ─── Permission separation (الإثبات المطلوب) ────────────────────────────────

it('comments.approve does NOT grant delete', function (): void {
    $c = cmodComment();
    $token = cmodScopedToken('comments.approve');

    // يستطيع تغيير الحالة…
    $this->withToken($token)
        ->patchJson("/api/v1/admin/comments/{$c->id}/status", ['status' => 'approved'])
        ->assertOk();
    // …لكن لا يستطيع الحذف.
    $this->withToken($token)
        ->deleteJson("/api/v1/admin/comments/{$c->id}")
        ->assertStatus(403);
});

it('comments.delete does NOT grant status change', function (): void {
    $c = cmodComment();
    $token = cmodScopedToken('comments.delete');

    // يستطيع الحذف…
    $this->withToken($token)
        ->deleteJson("/api/v1/admin/comments/{$c->id}")
        ->assertOk();
    // …لكن لا يستطيع تغيير الحالة.
    $c2 = cmodComment();
    $this->withToken($token)
        ->patchJson("/api/v1/admin/comments/{$c2->id}/status", ['status' => 'approved'])
        ->assertStatus(403);
});
