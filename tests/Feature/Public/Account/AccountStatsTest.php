<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Engagement;
use App\Models\EngagementCounter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// P2 — إحصاءات لوحة المستخدم: تجميع per-user من المحتوى/التفاعل القائم.

it('aggregates account stats from the user own content and engagement', function (): void {
    $user = User::factory()->create(['status' => UserStatus::Active]);

    $cat = Category::create([
        'name' => 'c-'.uniqid(), 'slug' => 'cat-'.uniqid(),
        'locale' => 'ar', 'scope' => 'both', 'status' => 'active',
    ]);

    $mkArticle = function (string $type, string $status) use ($cat, $user): Article {
        $a = Article::create([
            'primary_category_id' => $cat->id, 'type' => $type, 'status' => $status,
            'locale' => 'ar', 'title' => 'ع '.uniqid(), 'slug' => 'a-'.Str::random(8),
            'published_at' => $status === 'published' ? now()->subDay() : null,
        ]);
        // author_id بإسناد مباشر (يتجاوز سؤال fillable) ثمّ حفظ.
        $a->author_id = $user->id;
        $a->save();

        return $a;
    };

    $published = $mkArticle('news', 'published');
    $mkArticle('opinion', 'draft');

    Comment::create([
        'commentable_type' => 'App\Models\Article', 'commentable_id' => $published->id,
        'user_id' => $user->id, 'body' => 'تعليق', 'status' => 'approved',
    ]);
    Engagement::create([
        'engageable_type' => 'App\Models\Article', 'engageable_id' => $published->id,
        'user_id' => $user->id, 'actor_key' => 'u'.$user->id, 'type' => 'favorite',
    ]);
    EngagementCounter::create([
        'engageable_type' => 'App\Models\Article', 'engageable_id' => $published->id,
        'views' => 50, 'likes' => 0, 'dislikes' => 0, 'favorites' => 1,
    ]);

    $token = $user->createToken('public', ['user'])->plainTextToken;
    $res = $this->withToken($token)->getJson('/api/v1/account/stats')->assertOk();

    // جرد المحتوى.
    expect($res->json('data.content.articles'))->toBe(2);
    expect($res->json('data.content.news'))->toBe(1);
    expect($res->json('data.content.reels'))->toBe(0);
    expect($res->json('data.content.videos'))->toBe(0);

    // سير العمل.
    expect($res->json('data.workflow.published'))->toBe(1);
    expect($res->json('data.workflow.draft'))->toBe(1);
    expect($res->json('data.workflow.in_review'))->toBe(0);
    expect($res->json('data.workflow.rejected'))->toBe(0);

    // التفاعل.
    expect($res->json('data.engagement.comments'))->toBe(1);
    expect($res->json('data.engagement.favorites'))->toBe(1);
    expect($res->json('data.engagement.views'))->toBe(50);
});

it('requires authentication for /account/stats', function (): void {
    $this->getJson('/api/v1/account/stats')->assertUnauthorized();
});
