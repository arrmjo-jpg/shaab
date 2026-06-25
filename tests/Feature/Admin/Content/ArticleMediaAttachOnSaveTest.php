<?php

declare(strict_types=1);

use App\Actions\Admin\Media\PruneOrphanMediaAssetsAction;
use App\Actions\Admin\Media\StoreMediaAssetAction;
use App\Models\Article;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Storage::fake('uploads');
    config(['media-library.disk_name' => 'uploads']);
});

function aosEditor(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

function aosCat(): Category
{
    return Category::create([
        'name' => 'قسم '.uniqid(), 'locale' => 'ar', 'status' => 'active', 'scope' => 'both',
    ]);
}

function aosPayload(Category $cat, array $o = []): array
{
    return array_merge([
        'title' => 'عنوان '.uniqid(),
        'locale' => 'ar',
        'type' => 'news',
        'primary_category_id' => $cat->id,
        'excerpt' => 'ملخّص للاختبار.',
        'content_json' => tiptapDoc(),
    ], $o);
}

/** أصل مكتبة مرحّل (عبر الـ action — نفس مسار الرفع). */
function aosAsset(User $u, int $w = 600, int $h = 400): MediaAsset
{
    return (new StoreMediaAssetAction)->handle(
        UploadedFile::fake()->image('img-'.uniqid().'.jpg', $w, $h),
        $u,
    );
}

// ─── Create: attach-on-save ─────────────────────────────────────────────

it('attaches staged media when the article is first created', function (): void {
    [$u, $token] = aosEditor();
    $cat = aosCat();
    $cover = aosAsset($u, 800, 600);
    $g1 = aosAsset($u, 320, 240);
    $g2 = aosAsset($u, 321, 240);

    $res = $this->withToken($token)->postJson('/api/v1/admin/articles', aosPayload($cat, [
        'media' => [
            ['asset_id' => $cover->id, 'collection' => 'cover', 'position' => 0],
            ['asset_id' => $g1->id, 'collection' => 'gallery', 'position' => 0],
            ['asset_id' => $g2->id, 'collection' => 'gallery', 'position' => 1],
        ],
    ]))->assertCreated();

    expect($res->json('data.media.cover.id'))->toBe($cover->id);
    expect($res->json('data.media.gallery'))->toHaveCount(2);

    $a = Article::first();
    expect($a->mediaAssets()->count())->toBe(3);

    // تدقيق يدوي: حدث إسناد مُسجَّل
    expect(Activity::where('log_name', 'media')->where('event', 'media_attached')->count())->toBe(1);
});

// ─── Update: full sync (attach + detach) ────────────────────────────────

it('syncs media on update — attaches new and detaches removed', function (): void {
    [$u, $token] = aosEditor();
    $cat = aosCat();
    $a = $this->withToken($token)->postJson('/api/v1/admin/articles', aosPayload($cat, [
        'media' => [['asset_id' => aosAsset($u, 320, 240)->id, 'collection' => 'gallery', 'position' => 0]],
    ]))->json('data.id');

    $new = aosAsset($u, 333, 240);

    // إرسال مجموعة جديدة تماماً ⇒ يُفصل القديم ويُربط الجديد
    $res = $this->withToken($token)->putJson("/api/v1/admin/articles/{$a}", [
        'media' => [['asset_id' => $new->id, 'collection' => 'gallery', 'position' => 0]],
    ])->assertOk();

    expect($res->json('data.media.gallery'))->toHaveCount(1);
    expect($res->json('data.media.gallery.0.id'))->toBe($new->id);

    $article = Article::find($a);
    expect($article->mediaAssets()->count())->toBe(1);
    expect(Activity::where('log_name', 'media')->where('event', 'media_detached')->count())->toBe(1);
});

it('omitting the media key preserves existing attachments', function (): void {
    [$u, $token] = aosEditor();
    $cat = aosCat();
    $asset = aosAsset($u, 320, 240);
    $a = $this->withToken($token)->postJson('/api/v1/admin/articles', aosPayload($cat, [
        'media' => [['asset_id' => $asset->id, 'collection' => 'gallery', 'position' => 0]],
    ]))->json('data.id');

    // تحديث بلا مفتاح media ⇒ لا يمسّ الإسناد
    $this->withToken($token)->putJson("/api/v1/admin/articles/{$a}", ['title' => 'عنوان محدّث'])
        ->assertOk();

    expect(Article::find($a)->mediaAssets()->count())->toBe(1);
});

it('preserves editor-managed inline images when the studio syncs media', function (): void {
    [$u, $token] = aosEditor();
    $cat = aosCat();
    $gallery = aosAsset($u, 320, 240);
    $a = $this->withToken($token)->postJson('/api/v1/admin/articles', aosPayload($cat, [
        'media' => [['asset_id' => $gallery->id, 'collection' => 'gallery', 'position' => 0]],
    ]))->json('data.id');

    // المحرّر يُسند صورة inline مباشرةً (خارج الاستوديو)
    $inline = aosAsset($u, 500, 500);
    Article::find($a)->mediaAssets()->attach($inline->id, ['collection' => 'inline', 'position' => 0]);

    // حفظ من النموذج بحمولة gallery فقط (بلا inline) ⇒ يجب ألّا تُفصل inline
    $this->withToken($token)->putJson("/api/v1/admin/articles/{$a}", [
        'media' => [['asset_id' => $gallery->id, 'collection' => 'gallery', 'position' => 0]],
    ])->assertOk();

    $article = Article::find($a);
    expect($article->mediaAssets()->wherePivot('collection', 'inline')->count())->toBe(1);
    expect($article->mediaAssets()->wherePivot('collection', 'gallery')->count())->toBe(1);
});

it('logs a cover replacement when the cover asset changes', function (): void {
    [$u, $token] = aosEditor();
    $cat = aosCat();
    $cover1 = aosAsset($u, 800, 600);
    $a = $this->withToken($token)->postJson('/api/v1/admin/articles', aosPayload($cat, [
        'media' => [['asset_id' => $cover1->id, 'collection' => 'cover', 'position' => 0]],
    ]))->json('data.id');

    $cover2 = aosAsset($u, 801, 600);
    $this->withToken($token)->putJson("/api/v1/admin/articles/{$a}", [
        'media' => [['asset_id' => $cover2->id, 'collection' => 'cover', 'position' => 0]],
    ])->assertOk();

    expect(Activity::where('log_name', 'media')->where('event', 'media_replaced')->count())->toBe(1);
});

// ─── Validation ──────────────────────────────────────────────────────────

it('rejects more than one cover', function (): void {
    [$u, $token] = aosEditor();
    $cat = aosCat();

    $this->withToken($token)->postJson('/api/v1/admin/articles', aosPayload($cat, [
        'media' => [
            ['asset_id' => aosAsset($u, 800, 600)->id, 'collection' => 'cover', 'position' => 0],
            ['asset_id' => aosAsset($u, 801, 600)->id, 'collection' => 'cover', 'position' => 1],
        ],
    ]))->assertStatus(422)->assertJsonPath('errors.media.0', __('article.media.single_cover'));
});

// ─── Orphan cleanup ────────────────────────────────────────────────────

it('prunes staged assets that stay unattached past the TTL', function (): void {
    [$u] = aosEditor();

    // أبعاد مميّزة ⇒ checksum مميّز (تفادي dedupe في StoreMediaAssetAction)
    // أصل مهجور قديم (غير مُسنَد، أقدم من TTL)
    $orphan = aosAsset($u, 610, 400);
    $orphan->forceFill(['created_at' => now()->subHours(72)])->save();

    // أصل حديث (غير مُسنَد لكن ضمن نافذة التركيب)
    $recent = aosAsset($u, 611, 400);

    // أصل مُسنَد لمقال (يجب ألّا يُحذف رغم قِدمه)
    $attached = aosAsset($u, 612, 400);
    $attached->forceFill(['created_at' => now()->subHours(72)])->save();
    $cat = aosCat();
    $article = Article::create([
        'author_id' => $u->id, 'primary_category_id' => $cat->id,
        'type' => 'news', 'status' => 'draft', 'locale' => 'ar',
        'title' => 'مع وسائط', 'slug' => 's-'.uniqid(), 'content' => '<p>x</p>',
        'content_json' => tiptapDoc(),
    ]);
    $article->mediaAssets()->attach($attached->id, ['collection' => 'gallery', 'position' => 0]);

    $pruned = (new PruneOrphanMediaAssetsAction)->handle(48);

    expect($pruned)->toBe(1);
    expect(MediaAsset::find($orphan->id))->toBeNull();
    expect(MediaAsset::find($recent->id))->not->toBeNull();
    expect(MediaAsset::find($attached->id))->not->toBeNull();
    // حذف مُدقَّق (Eloquent delete)
    expect(Activity::where('log_name', 'media')->where('event', 'deleted')->count())->toBe(1);
});

it('never prunes settings/branding assets', function (): void {
    // أصل إعدادات على قرص public بمسار branding/ (ليس assets/)
    $branding = MediaAsset::create([
        'uuid' => (string) Str::uuid(),
        'disk' => 'public',
        'path' => 'branding/logos/'.uniqid().'.png',
        'filename' => 'logo.png',
        'original_name' => 'logo.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
        'size' => 1234,
        'visibility' => 'public',
    ]);
    $branding->forceFill(['created_at' => now()->subHours(200)])->save();

    $pruned = (new PruneOrphanMediaAssetsAction)->handle(48);

    expect($pruned)->toBe(0);
    expect(MediaAsset::find($branding->id))->not->toBeNull();
});
