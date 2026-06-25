<?php

declare(strict_types=1);

use App\Actions\Admin\Media\StoreMediaAssetAction;
use App\Models\Article;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Storage::fake('uploads');
    Queue::fake(); // لا معالجة فعليّة — نختبر الربط/الملكيّة فقط
});

/**
 * أصل وسائط مملوك للمستخدم عبر نفس خطّ الإدارة (StoreMediaAssetAction).
 * نُنوّع الأبعاد كي تختلف البصمة (checksum) فلا يُدمَج بالـ dedupe.
 */
function ownedArticleAsset(User $owner, int $dim = 120): MediaAsset
{
    return (new StoreMediaAssetAction)->handle(UploadedFile::fake()->image('a.jpg', $dim, $dim), $owner);
}

// ─── 1. ربط غلاف + OG يملكهما الكاتب → 201 + إسناد فعليّ ───────────────────
it('lets a writer attach an owned cover image and og image', function (): void {
    [$writer, $token] = publicWriterToken();
    $cat = categoryForScope('news');
    $cover = ownedArticleAsset($writer, 120);
    $og = ownedArticleAsset($writer, 140);

    $res = $this->withToken($token)->postJson('/api/v1/articles', publicArticlePayload('news', $cat->id, [
        'og_image_id' => $og->id,
        'media' => [['asset_id' => $cover->id, 'collection' => 'cover']],
    ]));

    $res->assertCreated();
    $article = Article::latest('id')->first();
    expect($article->og_image_id)->toBe($og->id);
    expect($article->mediaAssets()->count())->toBe(1);
    expect($article->mediaAssets()->first()->id)->toBe($cover->id);
    expect($article->mediaAssets()->first()->pivot->collection)->toBe('cover');
});

// ─── 2. منع ربط غلاف لا يملكه الكاتب (أصل كاتب آخر) → 422 ──────────────────
it('forbids attaching a cover asset owned by another writer (IDOR)', function (): void {
    [, $token] = publicWriterToken();
    $other = User::factory()->create(['is_writer' => true]);
    $cat = categoryForScope('news');
    $foreign = ownedArticleAsset($other, 160);

    $this->withToken($token)->postJson('/api/v1/articles', publicArticlePayload('news', $cat->id, [
        'media' => [['asset_id' => $foreign->id, 'collection' => 'cover']],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('media.0.asset_id');

    expect(Article::count())->toBe(0); // لم يُنشأ شيء
});

// ─── 3. منع ربط OG لا يملكه الكاتب → 422 ──────────────────────────────────
it('forbids attaching an og image owned by another writer (IDOR)', function (): void {
    [, $token] = publicWriterToken();
    $other = User::factory()->create(['is_writer' => true]);
    $cat = categoryForScope('news');
    $foreign = ownedArticleAsset($other, 180);

    $this->withToken($token)->postJson('/api/v1/articles', publicArticlePayload('news', $cat->id, [
        'og_image_id' => $foreign->id,
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('og_image_id');
});

// ─── 4. غلاف واحد كحدّ أقصى → 422 ─────────────────────────────────────────
it('rejects more than one cover image', function (): void {
    [$writer, $token] = publicWriterToken();
    $cat = categoryForScope('news');
    $c1 = ownedArticleAsset($writer, 120);
    $c2 = ownedArticleAsset($writer, 140);

    $this->withToken($token)->postJson('/api/v1/articles', publicArticlePayload('news', $cat->id, [
        'media' => [
            ['asset_id' => $c1->id, 'collection' => 'cover'],
            ['asset_id' => $c2->id, 'collection' => 'cover'],
        ],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('media');
});
