<?php

declare(strict_types=1);

use App\Actions\Admin\Media\PruneOrphanMediaAssetsAction;
use App\Actions\Admin\Media\StoreMediaAssetAction;
use App\Models\Article;
use App\Models\ArticleLiveUpdate;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Storage::fake('uploads');
    config(['media-library.disk_name' => 'uploads']);
});

function lumEditor(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

function lumLiveArticle(): Article
{
    $cat = Category::create([
        'name' => 'تغطية '.uniqid(), 'locale' => 'ar', 'status' => 'active', 'scope' => 'news',
    ]);

    return Article::create([
        'title' => 'حدث '.uniqid(), 'locale' => 'ar', 'type' => 'live', 'status' => 'published',
        'primary_category_id' => $cat->id, 'author_id' => User::factory()->create()->id,
        'content_json' => tiptapDoc(), 'content' => '<p>x</p>', 'published_at' => now()->subHour(),
        'slug' => 's-'.uniqid(),
    ]);
}

function lumAsset(User $u, int $w = 600, int $h = 400): MediaAsset
{
    return (new StoreMediaAssetAction)->handle(
        UploadedFile::fake()->image('u-'.uniqid().'.jpg', $w, $h),
        $u,
    );
}

// ─── Live update media attach-on-save (shared article_media) ─────────────

it('attaches media to a live update via the shared pivot', function (): void {
    [$u, $token] = lumEditor();
    $article = lumLiveArticle();
    $g1 = lumAsset($u, 320, 240);
    $g2 = lumAsset($u, 321, 240);

    $res = $this->withToken($token)->postJson("/api/v1/admin/articles/{$article->id}/live-updates", [
        'title' => 'تحديث بوسائط',
        'content_json' => tiptapDoc('سطر'),
        'media' => [
            ['asset_id' => $g1->id, 'collection' => 'gallery', 'position' => 0],
            ['asset_id' => $g2->id, 'collection' => 'gallery', 'position' => 1],
        ],
    ])->assertCreated();

    expect($res->json('data.media.gallery'))->toHaveCount(2);

    $update = ArticleLiveUpdate::first();
    expect($update->mediaAssets()->count())->toBe(2);
    // shared pivot: rows owned by live_update_id, article_id null
    expect($update->mediaAssets()->wherePivot('collection', 'gallery')->count())->toBe(2);
});

it('syncs live update media on edit (attach + detach)', function (): void {
    [$u, $token] = lumEditor();
    $article = lumLiveArticle();
    $first = lumAsset($u, 320, 240);
    $id = $this->withToken($token)->postJson("/api/v1/admin/articles/{$article->id}/live-updates", [
        'content_json' => tiptapDoc('سطر'),
        'media' => [['asset_id' => $first->id, 'collection' => 'gallery', 'position' => 0]],
    ])->json('data.id');

    $next = lumAsset($u, 333, 240);
    $res = $this->withToken($token)->putJson("/api/v1/admin/articles/{$article->id}/live-updates/{$id}", [
        'media' => [['asset_id' => $next->id, 'collection' => 'gallery', 'position' => 0]],
    ])->assertOk();

    expect($res->json('data.media.gallery'))->toHaveCount(1);
    expect($res->json('data.media.gallery.0.id'))->toBe($next->id);
    expect(ArticleLiveUpdate::find($id)->mediaAssets()->count())->toBe(1);
});

it('does not prune assets attached only to a live update', function (): void {
    [$u] = lumEditor();
    $article = lumLiveArticle();
    $update = ArticleLiveUpdate::create([
        'article_id' => $article->id, 'author_id' => $u->id,
        'content_json' => tiptapDoc('x'), 'content' => '<p>x</p>',
        'is_pinned' => false, 'happened_at' => now(),
    ]);

    $asset = lumAsset($u, 800, 600);
    $asset->forceFill(['created_at' => now()->subHours(72)])->save();
    $update->mediaAssets()->attach($asset->id, ['collection' => 'gallery', 'position' => 0]);

    $pruned = (new PruneOrphanMediaAssetsAction)->handle(48);

    expect($pruned)->toBe(0);
    expect(MediaAsset::find($asset->id))->not->toBeNull();
});
