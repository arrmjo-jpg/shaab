<?php

declare(strict_types=1);

use App\Actions\Admin\Media\StoreMediaAssetAction;
use App\Enums\MediaProcessingProfile;
use App\Jobs\GenerateMediaAssetConversionsJob;
use App\Jobs\TranscodeVideoAssetJob;
use App\Models\MediaAsset;
use App\Models\User;
use App\Rules\OwnedMediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Storage::fake('uploads');
});

/** كاتب: مستخدم بـ ability=user + is_writer=true. */
function mediaWriter(): array
{
    $u = User::factory()->create(['is_writer' => true]);
    $u->assignRole('user');

    return [$u, $u->createToken('public', ['user'])->plainTextToken];
}

// ─── 1. رفع صورة: نفس خطّ الإدارة (نفس الـJob) + ownership ────────────────
it('lets a writer upload an image through the same pipeline, owned by them', function (): void {
    Queue::fake();
    [$writer, $token] = mediaWriter();

    $res = $this->withToken($token)->post('/api/v1/media', [
        'file' => UploadedFile::fake()->image('photo.jpg', 1200, 800),
    ]);

    $res->assertCreated();
    assertSuccessContract($res);
    expect($res->json('data.is_image'))->toBeTrue();
    expect($res->json('data.width'))->toBe(1200);

    $asset = MediaAsset::firstOrFail();
    expect($asset->uploaded_by)->toBe($writer->id);              // الملكيّة مسجّلة
    Queue::assertPushed(GenerateMediaAssetConversionsJob::class); // نفس خطّ معالجة الصور
});

// ─── 2. رفع فيديو: نفس TranscodeVideoAssetJob + دورة الحالة + ownership ────
it('lets a writer upload a video that enters the same transcode pipeline', function (): void {
    Queue::fake();
    [$writer, $token] = mediaWriter();

    $res = $this->withToken($token)->post('/api/v1/media', [
        'file' => UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4'),
    ]);

    $res->assertCreated();
    expect($res->json('data.is_video'))->toBeTrue();
    expect($res->json('data.processing_status'))->toBe('queued'); // دخل دورة المعالجة

    $asset = MediaAsset::firstOrFail();
    expect($asset->uploaded_by)->toBe($writer->id);
    Queue::assertPushed(TranscodeVideoAssetJob::class);          // نفس خطّ الفيديو حرفيّاً
});

// ─── 3. ملف reel: نفس الخطّ بملف معالجة reel (لا مسار موازٍ) ──────────────
it('applies the reel processing profile through the same pipeline', function (): void {
    Queue::fake();
    [, $token] = mediaWriter();

    $res = $this->withToken($token)->post('/api/v1/media', [
        'file' => UploadedFile::fake()->create('reel.mp4', 2048, 'video/mp4'),
        'profile' => MediaProcessingProfile::Reel->value,
    ]);

    $res->assertCreated();
    $asset = MediaAsset::firstOrFail();
    expect($asset->processing_profile)->toBe(MediaProcessingProfile::Reel->value);
    Queue::assertPushed(TranscodeVideoAssetJob::class);
});

// ─── 4. التحقّق ينعكس من الإدارة تلقائيّاً: نوع غير مدعوم → 422 ────────────
it('rejects unsupported file types (admin validation reflected automatically)', function (): void {
    [, $token] = mediaWriter();

    $this->withToken($token)->post('/api/v1/media', [
        'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
    ])->assertUnprocessable();
});

// ─── 5. حارس IDOR: المالك يطّلع على حالة أصله ─────────────────────────────
// نُنشئ الأصل عبر نفس الأكشن (لا عبر HTTP) كي يبقى طلبٌ مصادَقٌ واحدٌ فقط لكل
// اختبار — فحارس auth في طبقة الاختبار يخزّن المستخدم عبر الطلبات المتعدّدة.
it('lets the owner read their own asset processing status', function (): void {
    Queue::fake();
    [$owner, $ownerToken] = mediaWriter();
    $asset = (new StoreMediaAssetAction)->handle(UploadedFile::fake()->image('a.jpg'), $owner);

    $this->withToken($ownerToken)->getJson("/api/v1/media/{$asset->uuid}")
        ->assertOk()->assertJsonPath('data.uuid', $asset->uuid);
});

// ─── 6. حارس IDOR: كاتب آخر لا يطّلع على أصل لا يملكه → 404 ────────────────
it('hides another writer asset processing status (IDOR guard → 404)', function (): void {
    Queue::fake();
    [$owner] = mediaWriter();
    [, $otherToken] = mediaWriter();
    $asset = (new StoreMediaAssetAction)->handle(UploadedFile::fake()->image('a.jpg'), $owner);

    $this->withToken($otherToken)->getJson("/api/v1/media/{$asset->uuid}")->assertNotFound();
});

// ─── 7. حارس الربط (OwnedMediaAsset): يمرّ للمالك ويفشل لغيره ─────────────
it('enforces ownership when binding a media_asset_id (OwnedMediaAsset rule)', function (): void {
    Queue::fake();
    [$owner] = mediaWriter();
    [$other] = mediaWriter();
    $asset = (new StoreMediaAssetAction)->handle(UploadedFile::fake()->image('a.jpg'), $owner);

    $fails = 0;
    $fail = function () use (&$fails): void {
        $fails++;
    };

    (new OwnedMediaAsset($owner->id))->validate('media_asset_id', $asset->id, $fail);
    expect($fails)->toBe(0); // المالك: يمرّ

    (new OwnedMediaAsset($other->id))->validate('media_asset_id', $asset->id, $fail);
    expect($fails)->toBe(1); // غير المالك: يفشل (حارس IDOR للربط)
});

// ─── 8. غير الكاتب (قارئ) → 403 ───────────────────────────────────────────
it('forbids a non-writer from uploading media', function (): void {
    $u = User::factory()->create(['is_writer' => false]);
    $u->assignRole('user');
    $token = $u->createToken('public', ['user'])->plainTextToken;

    $this->withToken($token)->post('/api/v1/media', [
        'file' => UploadedFile::fake()->image('x.jpg'),
    ])->assertStatus(403);
});

// ─── 9. غير المصادَق → 401 ────────────────────────────────────────────────
it('rejects unauthenticated uploads', function (): void {
    $this->postJson('/api/v1/media', [])->assertUnauthorized();
});

// ─── 10. dedupe بحسب الرافع: رفع الكاتب لبصمة يملكها آخر ⇒ أصل جديد مملوك له ─
// (الـdedupe العالميّ كان يعيد أصل المالك الآخر فيكسر OwnedMediaAsset عند الربط.)
it('does not dedupe a writer upload to another user asset', function (): void {
    Queue::fake();
    [$other] = mediaWriter();
    [$writer, $token] = mediaWriter();

    $tmp = UploadedFile::fake()->image('shared.jpg', 321, 321);
    $bytes = file_get_contents($tmp->getRealPath());
    $make = function () use ($bytes): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'dup').'.jpg';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'shared.jpg', 'image/jpeg', null, true);
    };

    // مستخدم آخر يملك أصلاً بنفس البصمة (dedupe عالميّ افتراضاً).
    $foreign = (new StoreMediaAssetAction)->handle($make(), $other);

    // الكاتب يرفع نفس البصمة → أصل جديد مملوك له (لا أصل الآخر).
    $res = $this->withToken($token)->post('/api/v1/media', ['file' => $make()]);
    $res->assertCreated();
    $newId = $res->json('data.id');

    expect($newId)->not->toBe($foreign->id);
    expect(MediaAsset::find($newId)->uploaded_by)->toBe($writer->id);
});
