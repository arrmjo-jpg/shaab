<?php

declare(strict_types=1);

use App\Enums\VideoStatus;
use App\Models\User;
use App\Models\Video;
use App\Support\Content\VideoAuthorizationGuard;
use App\Support\Content\VideoWorkflowGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function videoGuardReel(int $authorId, string $status): Video
{
    return Video::create([
        'author_id' => $authorId, 'status' => $status, 'locale' => 'ar',
        'title' => 'فيديو', 'slug' => 'video-'.uniqid(),
    ]);
}

// ─── VideoAuthorizationGuard ──────────────────────────────────────────────
it('treats super_admin and editor as editorial', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $editor = User::factory()->create();
    $editor->assignRole('editor');

    expect(VideoAuthorizationGuard::isEditorial($admin))->toBeTrue();
    expect(VideoAuthorizationGuard::isEditorial($editor))->toBeTrue();
});

it('does not treat a writer as editorial', function (): void {
    $writer = User::factory()->create(['is_writer' => true]);
    $writer->assignRole('user');

    expect(VideoAuthorizationGuard::isEditorial($writer))->toBeFalse();
});

it('allows a writer to create without denial', function (): void {
    $writer = User::factory()->create(['is_writer' => true]);
    $writer->assignRole('user');

    expect(VideoAuthorizationGuard::forCreate($writer, null))->toBeNull();
});

it('forbids a non-writer non-editorial user from creating', function (): void {
    $user = User::factory()->create(['is_writer' => false]);
    $user->assignRole('user');

    $denied = VideoAuthorizationGuard::forCreate($user, null);
    expect($denied?->getStatusCode())->toBe(403);
});

it('forbids a writer from passing a spoofed author_id', function (): void {
    $writer = User::factory()->create(['is_writer' => true]);
    $writer->assignRole('user');
    $victim = User::factory()->create();

    $denied = VideoAuthorizationGuard::forCreate($writer, $victim->id);
    expect($denied?->getStatusCode())->toBe(422);
});

it('rejects an editorial author_id that does not exist', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $denied = VideoAuthorizationGuard::forCreate($admin, 999999);
    expect($denied?->getStatusCode())->toBe(422);
});

it('self-assigns the writer regardless of requested author', function (): void {
    $writer = User::factory()->create(['is_writer' => true]);
    $writer->assignRole('user');
    $other = User::factory()->create();

    expect(VideoAuthorizationGuard::resolveAuthorId($writer, $other->id))->toBe($writer->id);
    expect(VideoAuthorizationGuard::resolveAuthorId($writer, null))->toBe($writer->id);
});

it('honors the requested author for an editorial user', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $author = User::factory()->create();

    expect(VideoAuthorizationGuard::resolveAuthorId($admin, $author->id))->toBe($author->id);
    expect(VideoAuthorizationGuard::resolveAuthorId($admin, null))->toBe($admin->id);
});

it('forbids a writer from updating a video they do not own', function (): void {
    $writer = User::factory()->create(['is_writer' => true]);
    $writer->assignRole('user');
    $other = User::factory()->create(['is_writer' => true]);
    $video = videoGuardReel($other->id, 'draft');

    $denied = VideoAuthorizationGuard::forUpdate($writer, $video);
    expect($denied?->getStatusCode())->toBe(403);
});

// ─── VideoWorkflowGuard ───────────────────────────────────────────────────
it('rejects a transition outside the matrix', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $video = videoGuardReel($admin->id, 'published'); // published → draft غير مسموح

    $denied = VideoWorkflowGuard::check($admin, $video, VideoStatus::Draft, null);
    expect($denied?->getStatusCode())->toBe(422);
});

it('allows the owning writer to submit a draft', function (): void {
    $writer = User::factory()->create(['is_writer' => true]);
    $writer->assignRole('user');
    $video = videoGuardReel($writer->id, 'draft');

    expect(VideoWorkflowGuard::check($writer, $video, VideoStatus::Submitted, null))->toBeNull();
});

it('forbids a writer from publishing directly', function (): void {
    $writer = User::factory()->create(['is_writer' => true]);
    $writer->assignRole('user');
    $video = videoGuardReel($writer->id, 'draft');

    $denied = VideoWorkflowGuard::check($writer, $video, VideoStatus::Published, null);
    expect($denied?->getStatusCode())->toBe(403);
});

it('forbids a writer from transitioning a video they do not own', function (): void {
    $writer = User::factory()->create(['is_writer' => true]);
    $writer->assignRole('user');
    $other = User::factory()->create(['is_writer' => true]);
    $video = videoGuardReel($other->id, 'draft');

    $denied = VideoWorkflowGuard::check($writer, $video, VideoStatus::Submitted, null);
    expect($denied?->getStatusCode())->toBe(403);
});

it('allows an editorial super_admin to publish', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $video = videoGuardReel($admin->id, 'submitted');

    expect(VideoWorkflowGuard::check($admin, $video, VideoStatus::Published, null))->toBeNull();
});

it('forbids an editorial editor lacking videos.publish from publishing', function (): void {
    $editor = User::factory()->create();
    $editor->assignRole('editor'); // editor seeded بلا صلاحيات
    $video = videoGuardReel($editor->id, 'submitted');

    $denied = VideoWorkflowGuard::check($editor, $video, VideoStatus::Published, null);
    expect($denied?->getStatusCode())->toBe(403);
});

it('requires a future date for scheduling', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $video = videoGuardReel($admin->id, 'draft');

    expect(VideoWorkflowGuard::check($admin, $video, VideoStatus::Scheduled, null)?->getStatusCode())->toBe(422);
    expect(VideoWorkflowGuard::check($admin, $video, VideoStatus::Scheduled, Carbon::now()->subDay())?->getStatusCode())->toBe(422);
});
