<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

/** كاتب: مستخدم بـ ability=user + is_writer=true. */
function videoWriterToken(): array
{
    $u = User::factory()->create(['is_writer' => true]);
    $u->assignRole('user');

    return [$u, $u->createToken('public', ['user'])->plainTextToken];
}

/** حمولة إنشاء فيديو صالحة للكاتب — المصدر مطلوب الآن (مثل الإدارة)؛ نُمرّر رابطاً خارجيّاً افتراضيّاً. */
function videoPayload(array $extra = []): array
{
    return array_merge([
        'title' => 'فيديو تجريبي',
        'locale' => 'ar',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ], $extra);
}

function makeVideo(int $authorId, string $status): Video
{
    return Video::create([
        'author_id' => $authorId,
        'status' => $status,
        'locale' => 'ar',
        'title' => 'فيديو '.$status.'-'.uniqid(),
        'slug' => 'video-'.uniqid(),
    ]);
}

// ─── 1. الكاتب يُنشئ Video draft ──────────────────────────────────────────
it('lets a writer create a video draft', function (): void {
    [$writer, $token] = videoWriterToken();

    $response = $this->withToken($token)->postJson('/api/v1/videos', videoPayload());

    $response->assertCreated();
    expect($response->json('data.status'))->toBe('draft');
    $video = Video::latest('id')->first();
    expect($video->author_id)->toBe($writer->id);
    expect(Video::where('author_id', $writer->id)->where('status', 'draft')->exists())->toBeTrue();
});

// ─── 2. غير الكاتب → 403 ──────────────────────────────────────────────────
it('returns 403 for a non-writer user', function (): void {
    $u = User::factory()->create(['is_writer' => false]);
    $u->assignRole('user');
    $token = $u->createToken('public', ['user'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/videos', videoPayload())->assertStatus(403);
});

// ─── 3. author_id spoof يُتجاهَل (إسناد ذاتي) ─────────────────────────────
it('ignores a spoofed author_id and self-assigns the writer', function (): void {
    [$writer, $token] = videoWriterToken();
    $victim = User::factory()->create();

    $response = $this->withToken($token)->postJson('/api/v1/videos', videoPayload(['author_id' => $victim->id]));

    $response->assertCreated();
    $video = Video::latest('id')->first();
    expect($video->author_id)->toBe($writer->id);
    expect($video->author_id)->not->toBe($victim->id);
});

// ─── 4. الحقول التحريرية لا تؤثّر ─────────────────────────────────────────
it('does not let a writer set editorial fields', function (): void {
    [, $token] = videoWriterToken();

    $response = $this->withToken($token)->postJson('/api/v1/videos', videoPayload([
        'is_featured' => true,
        'sort_order' => 99,
        'visibility' => 'private',
    ]));

    $response->assertCreated();
    $video = Video::latest('id')->first();
    expect($video->is_featured)->toBeFalse();
    expect((int) $video->sort_order)->toBe(0);
    // visibility لم تُرفَّع — تبقى الافتراضي (public) لا القيمة المُمرَّرة.
    expect($video->visibility->value)->toBe('public');
});

// ─── 5. الكاتب: draft → submitted ────────────────────────────────────────
it('lets a writer submit their own draft for review', function (): void {
    [$writer, $token] = videoWriterToken();
    $video = makeVideo($writer->id, 'draft');

    $response = $this->withToken($token)
        ->patchJson("/api/v1/videos/{$video->id}/status", ['status' => 'submitted']);

    $response->assertOk();
    expect($video->fresh()->status->value)->toBe('submitted');
});

// ─── 6. منع publish المباشر للكاتب ────────────────────────────────────────
it('forbids a writer from publishing directly', function (): void {
    [$writer, $token] = videoWriterToken();
    $video = makeVideo($writer->id, 'draft');

    // status=published يُرفض في الـ FormRequest (submitted فقط) → 422
    $this->withToken($token)
        ->patchJson("/api/v1/videos/{$video->id}/status", ['status' => 'published'])
        ->assertStatus(422);

    expect($video->fresh()->status->value)->toBe('draft');
});

// ─── 7. منع إرسال فيديو لا يملكه الكاتب ───────────────────────────────────
it('forbids a writer from submitting a video they do not own', function (): void {
    [, $token] = videoWriterToken();
    $other = User::factory()->create(['is_writer' => true]);
    $video = makeVideo($other->id, 'draft');

    $this->withToken($token)
        ->patchJson("/api/v1/videos/{$video->id}/status", ['status' => 'submitted'])
        ->assertStatus(403);

    expect($video->fresh()->status->value)->toBe('draft');
});

// ─── 8. GET /videos/mine: فيديوهات الكاتب فقط + status ظاهر ───────────────
it('lists only the writer own videos with status visible', function (): void {
    [$writer, $token] = videoWriterToken();
    $other = User::factory()->create(['is_writer' => true]);

    $mine1 = makeVideo($writer->id, 'draft');
    $mine2 = makeVideo($writer->id, 'submitted');
    makeVideo($other->id, 'published'); // فيديو كاتب آخر — يجب ألا يظهر

    $response = $this->withToken($token)->getJson('/api/v1/videos/mine');

    $response->assertOk();
    assertSuccessContract($response);
    expect($response->json('meta.pagination.total'))->toBe(2);

    $statuses = collect($response->json('data'))->pluck('status')->sort()->values()->all();
    expect($statuses)->toBe(['draft', 'submitted']);

    $ids = collect($response->json('data'))->pluck('id')->sort()->values()->all();
    expect($ids)->toBe(collect([$mine1->id, $mine2->id])->sort()->values()->all());
});

// ─── 9. مصدر مطلوب: لا رفع ولا رابط → 422 (مطابقة الإدارة) ─────────────────
it('requires a media source (upload or external link)', function (): void {
    [, $token] = videoWriterToken();

    // بلا media_asset_id ولا source_url — يُرفَض (كلاهما required_without الآخر).
    $this->withToken($token)
        ->postJson('/api/v1/videos', ['title' => 'بلا مصدر', 'locale' => 'ar'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['media_asset_id', 'source_url']);
});
