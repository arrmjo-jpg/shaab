<?php

declare(strict_types=1);

use App\Models\Reel;
use App\Models\User;
use App\Support\Content\ReelAuthorizationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

// ─── isEditorial ──────────────────────────────────────────────────────────
it('treats super_admin and editor as editorial', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $editor = User::factory()->create();
    $editor->assignRole('editor');

    expect(ReelAuthorizationGuard::isEditorial($admin))->toBeTrue();
    expect(ReelAuthorizationGuard::isEditorial($editor))->toBeTrue();
});

it('does not treat a writer (is_writer) as editorial', function (): void {
    $writer = User::factory()->create(['is_writer' => true]);
    $writer->assignRole('user');

    expect(ReelAuthorizationGuard::isEditorial($writer))->toBeFalse();
});

// ─── forCreate ────────────────────────────────────────────────────────────
it('allows a writer to create without a denial', function (): void {
    $writer = User::factory()->create(['is_writer' => true]);
    $writer->assignRole('user');

    expect(ReelAuthorizationGuard::forCreate($writer, null))->toBeNull();
});

it('forbids a non-writer non-editorial user from creating', function (): void {
    $user = User::factory()->create(['is_writer' => false]);
    $user->assignRole('user');

    $denied = ReelAuthorizationGuard::forCreate($user, null);
    expect($denied)->not->toBeNull();
    expect($denied->getStatusCode())->toBe(403);
});

it('forbids a writer from passing a spoofed author_id', function (): void {
    $writer = User::factory()->create(['is_writer' => true]);
    $writer->assignRole('user');
    $victim = User::factory()->create();

    $denied = ReelAuthorizationGuard::forCreate($writer, $victim->id);
    expect($denied)->not->toBeNull();
    expect($denied->getStatusCode())->toBe(422);
});

it('lets an editorial user pass an existing author_id', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $author = User::factory()->create();

    expect(ReelAuthorizationGuard::forCreate($admin, $author->id))->toBeNull();
});

it('rejects an editorial author_id that does not exist', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $denied = ReelAuthorizationGuard::forCreate($admin, 999999);
    expect($denied)->not->toBeNull();
    expect($denied->getStatusCode())->toBe(422);
});

// ─── resolveAuthorId ──────────────────────────────────────────────────────
it('self-assigns the writer regardless of requested author', function (): void {
    $writer = User::factory()->create(['is_writer' => true]);
    $writer->assignRole('user');
    $other = User::factory()->create();

    // الكاتب يُربط ذاتياً حتى لو مُرّر author آخر.
    expect(ReelAuthorizationGuard::resolveAuthorId($writer, $other->id))->toBe($writer->id);
    expect(ReelAuthorizationGuard::resolveAuthorId($writer, null))->toBe($writer->id);
});

it('honors the requested author for an editorial user', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $author = User::factory()->create();

    expect(ReelAuthorizationGuard::resolveAuthorId($admin, $author->id))->toBe($author->id);
    // بلا author مُرسَل → يقع على الفاعل التحريري.
    expect(ReelAuthorizationGuard::resolveAuthorId($admin, null))->toBe($admin->id);
});

// ─── forUpdate ────────────────────────────────────────────────────────────
it('lets an editorial user update any reel', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $owner = User::factory()->create(['is_writer' => true]);
    $reel = Reel::create([
        'author_id' => $owner->id, 'status' => 'draft', 'locale' => 'ar',
        'title' => 'ريل', 'slug' => 'reel-'.uniqid(),
    ]);

    expect(ReelAuthorizationGuard::forUpdate($admin, $reel))->toBeNull();
});

it('forbids a writer from updating a reel they do not own', function (): void {
    $writer = User::factory()->create(['is_writer' => true]);
    $writer->assignRole('user');
    $other = User::factory()->create(['is_writer' => true]);
    $reel = Reel::create([
        'author_id' => $other->id, 'status' => 'draft', 'locale' => 'ar',
        'title' => 'ريل غيري', 'slug' => 'reel-'.uniqid(),
    ]);

    $denied = ReelAuthorizationGuard::forUpdate($writer, $reel);
    expect($denied)->not->toBeNull();
    expect($denied->getStatusCode())->toBe(403);
});
