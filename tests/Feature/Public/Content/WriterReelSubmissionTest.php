<?php

declare(strict_types=1);

use App\Models\Reel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

/** كاتب: مستخدم بـ ability=user + is_writer=true. */
function reelWriterToken(): array
{
    $u = User::factory()->create(['is_writer' => true]);
    $u->assignRole('user');

    return [$u, $u->createToken('public', ['user'])->plainTextToken];
}

/** حمولة إنشاء ريل صالحة للكاتب (بلا media — V1). */
function reelPayload(array $extra = []): array
{
    return array_merge([
        'title' => 'ريل تجريبي',
        'locale' => 'ar',
    ], $extra);
}

function publicMakeReel(int $authorId, string $status): Reel
{
    return Reel::create([
        'author_id' => $authorId,
        'status' => $status,
        'locale' => 'ar',
        'title' => 'ريل '.$status.'-'.uniqid(),
        'slug' => 'reel-'.uniqid(),
    ]);
}

// ─── 1. الكاتب يُنشئ Reel draft ───────────────────────────────────────────
it('lets a writer create a reel draft', function (): void {
    [$writer, $token] = reelWriterToken();

    $response = $this->withToken($token)->postJson('/api/v1/reels', reelPayload());

    $response->assertCreated();
    expect($response->json('data.status'))->toBe('draft');
    expect(Reel::where('author_id', $writer->id)->where('status', 'draft')->exists())->toBeTrue();
});

// ─── 2. غير الكاتب → 403 ──────────────────────────────────────────────────
it('returns 403 for a non-writer user', function (): void {
    $u = User::factory()->create(['is_writer' => false]);
    $u->assignRole('user');
    $token = $u->createToken('public', ['user'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/reels', reelPayload())->assertStatus(403);
});

// ─── 3. author_id spoof يُتجاهَل (إسناد ذاتي) ─────────────────────────────
it('ignores a spoofed author_id and self-assigns the writer', function (): void {
    [$writer, $token] = reelWriterToken();
    $victim = User::factory()->create();

    // author_id مُسقَط من القواعد → 'sometimes' لن يصله؛ حتى لو وصل، الحارس يرفض/يُسند ذاتياً.
    $response = $this->withToken($token)->postJson('/api/v1/reels', reelPayload(['author_id' => $victim->id]));

    $response->assertCreated();
    $reel = Reel::latest('id')->first();
    expect($reel->author_id)->toBe($writer->id);
    expect($reel->author_id)->not->toBe($victim->id);
});

// ─── 4. الحقول التحريرية لا تؤثّر ─────────────────────────────────────────
it('does not let a writer set editorial fields', function (): void {
    [, $token] = reelWriterToken();

    $response = $this->withToken($token)->postJson('/api/v1/reels', reelPayload([
        'is_featured' => true,
        'sort_order' => 99,
    ]));

    $response->assertCreated();
    $reel = Reel::latest('id')->first();
    expect($reel->is_featured)->toBeFalse();
    expect((int) $reel->sort_order)->toBe(0);
});

// ─── 5. الكاتب: draft → submitted ────────────────────────────────────────
it('lets a writer submit their own draft for review', function (): void {
    [$writer, $token] = reelWriterToken();
    $reel = publicMakeReel($writer->id, 'draft');

    $response = $this->withToken($token)
        ->patchJson("/api/v1/reels/{$reel->id}/status", ['status' => 'submitted']);

    $response->assertOk();
    expect($reel->fresh()->status->value)->toBe('submitted');
});

// ─── 6. منع publish المباشر للكاتب ────────────────────────────────────────
it('forbids a writer from publishing directly', function (): void {
    [$writer, $token] = reelWriterToken();
    $reel = publicMakeReel($writer->id, 'draft');

    // status=published يُرفض في الـ FormRequest (submitted فقط) → 422
    $this->withToken($token)
        ->patchJson("/api/v1/reels/{$reel->id}/status", ['status' => 'published'])
        ->assertStatus(422);

    expect($reel->fresh()->status->value)->toBe('draft');
});

// ─── 7. منع إرسال ريل لا يملكه الكاتب ─────────────────────────────────────
it('forbids a writer from submitting a reel they do not own', function (): void {
    [, $token] = reelWriterToken();
    $other = User::factory()->create(['is_writer' => true]);
    $reel = publicMakeReel($other->id, 'draft');

    $this->withToken($token)
        ->patchJson("/api/v1/reels/{$reel->id}/status", ['status' => 'submitted'])
        ->assertStatus(403);

    expect($reel->fresh()->status->value)->toBe('draft');
});

// ─── 8. GET /reels/mine: ريلز الكاتب فقط + status ظاهر ────────────────────
it('lists only the writer own reels with status visible', function (): void {
    [$writer, $token] = reelWriterToken();
    $other = User::factory()->create(['is_writer' => true]);

    $mine1 = publicMakeReel($writer->id, 'draft');
    $mine2 = publicMakeReel($writer->id, 'submitted');
    publicMakeReel($other->id, 'published'); // ريل كاتب آخر — يجب ألا يظهر

    $response = $this->withToken($token)->getJson('/api/v1/reels/mine');

    $response->assertOk();
    assertSuccessContract($response);
    expect($response->json('meta.pagination.total'))->toBe(2);

    $statuses = collect($response->json('data'))->pluck('status')->sort()->values()->all();
    expect($statuses)->toBe(['draft', 'submitted']);

    $ids = collect($response->json('data'))->pluck('id')->sort()->values()->all();
    expect($ids)->toBe(collect([$mine1->id, $mine2->id])->sort()->values()->all());
});
