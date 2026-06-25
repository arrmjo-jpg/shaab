<?php

declare(strict_types=1);

use App\Enums\ReelStatus;
use App\Models\Reel;
use App\Models\User;
use App\Support\Content\ReelWorkflowGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function guardReel(int $authorId, string $status): Reel
{
    return Reel::create([
        'author_id' => $authorId, 'status' => $status, 'locale' => 'ar',
        'title' => 'ريل', 'slug' => 'reel-'.uniqid(),
    ]);
}

// ─── انتقال غير موجود في المصفوفة ─────────────────────────────────────────
it('rejects a transition outside the matrix', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $reel = guardReel($admin->id, 'published'); // published → draft غير مسموح

    $denied = ReelWorkflowGuard::check($admin, $reel, ReelStatus::Draft, null);
    expect($denied)->not->toBeNull();
    expect($denied->getStatusCode())->toBe(422);
});

it('rejects a no-op transition (from === to)', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $reel = guardReel($admin->id, 'draft');

    $denied = ReelWorkflowGuard::check($admin, $reel, ReelStatus::Draft, null);
    expect($denied)->not->toBeNull();
    expect($denied->getStatusCode())->toBe(422);
});

// ─── الكاتب: draft → submitted مسموح (يملك) ───────────────────────────────
it('allows the owning writer to submit a draft', function (): void {
    $writer = User::factory()->create(['is_writer' => true]);
    $writer->assignRole('user');
    $reel = guardReel($writer->id, 'draft');

    expect(ReelWorkflowGuard::check($writer, $reel, ReelStatus::Submitted, null))->toBeNull();
});

it('allows the owning writer to resubmit a rejected reel', function (): void {
    $writer = User::factory()->create(['is_writer' => true]);
    $writer->assignRole('user');
    $reel = guardReel($writer->id, 'rejected');

    expect(ReelWorkflowGuard::check($writer, $reel, ReelStatus::Submitted, null))->toBeNull();
});

// ─── الكاتب لا ينشر مباشرة ────────────────────────────────────────────────
it('forbids a writer from publishing directly', function (): void {
    $writer = User::factory()->create(['is_writer' => true]);
    $writer->assignRole('user');
    $reel = guardReel($writer->id, 'draft');

    $denied = ReelWorkflowGuard::check($writer, $reel, ReelStatus::Published, null);
    expect($denied)->not->toBeNull();
    expect($denied->getStatusCode())->toBe(403);
});

// ─── الكاتب لا يحرّك ريل لا يملكه ─────────────────────────────────────────
it('forbids a writer from transitioning a reel they do not own', function (): void {
    $writer = User::factory()->create(['is_writer' => true]);
    $writer->assignRole('user');
    $other = User::factory()->create(['is_writer' => true]);
    $reel = guardReel($other->id, 'draft');

    $denied = ReelWorkflowGuard::check($writer, $reel, ReelStatus::Submitted, null);
    expect($denied)->not->toBeNull();
    expect($denied->getStatusCode())->toBe(403);
});

// ─── التحريري: submitted → published مسموح (super_admin له كل الصلاحيات) ───
it('allows an editorial super_admin to publish', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $reel = guardReel($admin->id, 'submitted');

    expect(ReelWorkflowGuard::check($admin, $reel, ReelStatus::Published, null))->toBeNull();
});

// ─── التحريري بلا reels.publish يُمنع من النشر (البوّابة الدقيقة) ──────────
it('forbids an editorial editor lacking reels.publish from publishing', function (): void {
    $editor = User::factory()->create();
    $editor->assignRole('editor'); // editor seeded بلا صلاحيات (الإنتاج مُقلَّم)
    $reel = guardReel($editor->id, 'submitted');

    $denied = ReelWorkflowGuard::check($editor, $reel, ReelStatus::Published, null);
    expect($denied)->not->toBeNull();
    expect($denied->getStatusCode())->toBe(403);
});

// ─── الجدولة تتطلّب تاريخاً مستقبلياً ─────────────────────────────────────
it('requires a future date for scheduling', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $reel = guardReel($admin->id, 'draft');

    // بلا تاريخ → رفض
    $deniedNull = ReelWorkflowGuard::check($admin, $reel, ReelStatus::Scheduled, null);
    expect($deniedNull)->not->toBeNull();
    expect($deniedNull->getStatusCode())->toBe(422);

    // تاريخ ماضٍ → رفض
    $deniedPast = ReelWorkflowGuard::check($admin, $reel, ReelStatus::Scheduled, Carbon::now()->subDay());
    expect($deniedPast)->not->toBeNull();
    expect($deniedPast->getStatusCode())->toBe(422);
});
