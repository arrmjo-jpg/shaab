<?php

declare(strict_types=1);

use App\Actions\Admin\Content\TransitionArticleStatusAction;
use App\Actions\Admin\Content\TransitionReelStatusAction;
use App\Actions\Admin\VideoLibrary\TransitionVideoStatusAction;
use App\Models\Article;
use App\Models\Category;
use App\Models\Reel;
use App\Models\User;
use App\Models\Video;
use App\Notifications\ContentStatusChanged;
use App\Support\Notifications\WriterNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

/** كاتب: مستخدم بـ ability=user + is_writer=true. */
function notifWriter(): array
{
    $u = User::factory()->create(['is_writer' => true]);
    $u->assignRole('user');

    return [$u, $u->createToken('public', ['user'])->plainTextToken];
}

/** محرّر تحريري (super_admin) — يمرّر حارس الانتقال. */
function notifEditor(): User
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u;
}

function notifMakeArticle(?int $authorId, string $status): Article
{
    $category = Category::create([
        'name' => 'c-'.uniqid(),
        'slug' => 'cat-'.uniqid(),
        'locale' => 'ar',
        'scope' => 'both',
        'status' => 'active',
    ]);

    return Article::create([
        'author_id' => $authorId,
        'primary_category_id' => $category->id,
        'type' => 'news',
        'status' => $status,
        'locale' => 'ar',
        'title' => 'مقال '.$status.'-'.uniqid(),
        'slug' => 'article-'.uniqid(),
    ]);
}

function notifMakeReel(int $authorId, string $status): Reel
{
    return Reel::create([
        'author_id' => $authorId,
        'status' => $status,
        'locale' => 'ar',
        'title' => 'ريل '.$status.'-'.uniqid(),
        'slug' => 'reel-'.uniqid(),
    ]);
}

function notifMakeVideo(int $authorId, string $status): Video
{
    return Video::create([
        'author_id' => $authorId,
        'status' => $status,
        'locale' => 'ar',
        'title' => 'فيديو '.$status.'-'.uniqid(),
        'slug' => 'video-'.uniqid(),
    ]);
}

/** يَزرع إشعاراً مباشرةً للمستخدم (قناة database، متزامن). */
function notifSeed(User $u, string $status = 'published'): void
{
    $u->notify(new ContentStatusChanged('article', 100, 'عنوان', 'slug-'.uniqid(), $status));
}

// ─── A. منطق WriterNotifier (نشر/رفض فقط + حارس بلا-كاتب) ──────────────────

it('dispatches a database notification to the author on published', function (): void {
    [$writer] = notifWriter();
    $article = notifMakeArticle($writer->id, 'submitted');

    WriterNotifier::contentStatusChanged($article, 'article', 'published');

    expect($writer->notifications()->count())->toBe(1);
    $data = $writer->notifications()->first()->data;
    expect($data['status'])->toBe('published');
    expect($data['content_type'])->toBe('article');
    expect((int) $data['content_id'])->toBe($article->id);
});

it('dispatches a database notification on rejected', function (): void {
    [$writer] = notifWriter();
    $reel = notifMakeReel($writer->id, 'submitted');

    WriterNotifier::contentStatusChanged($reel, 'reel', 'rejected');

    expect($writer->notifications()->count())->toBe(1);
    expect($writer->notifications()->first()->data['status'])->toBe('rejected');
});

it('does NOT dispatch on non publish/reject statuses', function (string $status): void {
    [$writer] = notifWriter();
    $article = notifMakeArticle($writer->id, 'submitted');

    WriterNotifier::contentStatusChanged($article, 'article', $status);

    expect($writer->notifications()->count())->toBe(0);
})->with(['in_review', 'submitted', 'scheduled', 'draft', 'archived']);

it('does NOT dispatch when content has no author', function (): void {
    $article = notifMakeArticle(null, 'submitted');

    WriterNotifier::contentStatusChanged($article, 'article', 'published');

    expect(DatabaseNotification::count())->toBe(0);
});

// ─── B. تكامل عبر الـ Actions الحقيقية (post-commit) ──────────────────────

it('notifies the author when an editor publishes their article', function (): void {
    [$writer] = notifWriter();
    $article = notifMakeArticle($writer->id, 'submitted');

    $response = (new TransitionArticleStatusAction)->handle($article, ['status' => 'published'], notifEditor());

    expect($response->getStatusCode())->toBe(200);
    expect($writer->notifications()->count())->toBe(1);
    expect($writer->notifications()->first()->data['status'])->toBe('published');
});

it('notifies the author when an editor rejects their article', function (): void {
    [$writer] = notifWriter();
    $article = notifMakeArticle($writer->id, 'submitted');

    (new TransitionArticleStatusAction)->handle($article, ['status' => 'rejected'], notifEditor());

    expect($writer->notifications()->count())->toBe(1);
    expect($writer->notifications()->first()->data['status'])->toBe('rejected');
});

it('notifies the author when an editor rejects their reel', function (): void {
    [$writer] = notifWriter();
    $reel = notifMakeReel($writer->id, 'submitted');

    (new TransitionReelStatusAction)->handle($reel, ['status' => 'rejected'], notifEditor());

    expect($writer->notifications()->count())->toBe(1);
    expect($writer->notifications()->first()->data['content_type'])->toBe('reel');
});

it('notifies the author when an editor rejects their video', function (): void {
    [$writer] = notifWriter();
    $video = notifMakeVideo($writer->id, 'submitted');

    (new TransitionVideoStatusAction)->handle($video, ['status' => 'rejected'], notifEditor());

    expect($writer->notifications()->count())->toBe(1);
    expect($writer->notifications()->first()->data['content_type'])->toBe('video');
});

it('does NOT notify on an in_review transition', function (): void {
    [$writer] = notifWriter();
    $article = notifMakeArticle($writer->id, 'submitted');

    (new TransitionArticleStatusAction)->handle($article, ['status' => 'in_review'], notifEditor());

    expect($writer->notifications()->count())->toBe(0);
});

// ─── C. سطح API الإشعارات (محصور بالمالك) ─────────────────────────────────

it('lists only the writer own notifications with unread count', function (): void {
    [$a, $tokenA] = notifWriter();
    [$b] = notifWriter();

    notifSeed($a, 'published');
    notifSeed($a, 'rejected');
    notifSeed($b, 'published');

    $response = $this->withToken($tokenA)->getJson('/api/v1/notifications');

    $response->assertOk();
    assertSuccessContract($response);
    expect($response->json('meta.pagination.total'))->toBe(2);
    expect($response->json('meta.pagination.unread'))->toBe(2);
    expect(count($response->json('data')))->toBe(2);
});

it('returns the unread count', function (): void {
    [$a, $tokenA] = notifWriter();
    notifSeed($a);
    notifSeed($a);

    $response = $this->withToken($tokenA)->getJson('/api/v1/notifications/unread-count');

    $response->assertOk();
    expect($response->json('data.unread'))->toBe(2);
});

it('marks a single notification as read', function (): void {
    [$a, $tokenA] = notifWriter();
    notifSeed($a);
    $id = $a->notifications()->first()->id;

    $this->withToken($tokenA)->patchJson("/api/v1/notifications/{$id}/read")->assertOk();

    expect($a->unreadNotifications()->count())->toBe(0);
});

it('marks all notifications as read', function (): void {
    [$a, $tokenA] = notifWriter();
    notifSeed($a);
    notifSeed($a);
    notifSeed($a);

    $this->withToken($tokenA)->patchJson('/api/v1/notifications/read-all')->assertOk();

    expect($a->unreadNotifications()->count())->toBe(0);
});

it('forbids marking another writer notification (404)', function (): void {
    [, $tokenA] = notifWriter();
    [$b] = notifWriter();
    notifSeed($b);
    $bId = $b->notifications()->first()->id;

    $this->withToken($tokenA)->patchJson("/api/v1/notifications/{$bId}/read")->assertStatus(404);

    expect($b->unreadNotifications()->count())->toBe(1);
});

it('returns 403 for a non-writer user', function (): void {
    $u = User::factory()->create(['is_writer' => false]);
    $u->assignRole('user');
    $token = $u->createToken('public', ['user'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/notifications')->assertStatus(403);
});

it('returns 401 for an unauthenticated request', function (): void {
    $this->getJson('/api/v1/notifications')->assertStatus(401);
});
