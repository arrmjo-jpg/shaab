<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

/** كاتب: مستخدم بـ ability=user + is_writer=true. */
function publicWriterToken(): array
{
    $u = User::factory()->create(['is_writer' => true]);
    $u->assignRole('user');

    return [$u, $u->createToken('public', ['user'])->plainTextToken];
}

/** قسم بنطاق مطابق لنوع المحتوى (news|opinion|both). */
function categoryForScope(string $scope = 'both'): Category
{
    return Category::create([
        'name' => 'c-'.uniqid(),
        'slug' => 'cat-'.uniqid(),
        'locale' => 'ar',
        'scope' => $scope,
        'status' => 'active',
    ]);
}

/** حمولة إنشاء صالحة للكاتب. */
function publicArticlePayload(string $type, int $categoryId, array $extra = []): array
{
    return array_merge([
        'title' => 'عنوان تجريبي',
        'locale' => 'ar',
        'type' => $type,
        'primary_category_id' => $categoryId,
        'content_json' => tiptapDoc(),
    ], $extra);
}

// ─── 1. Writer يُنشئ News Draft ───────────────────────────────────────────
it('lets a writer create a news draft', function (): void {
    [$user, $token] = publicWriterToken();
    $cat = categoryForScope('news');

    $response = $this->withToken($token)
        ->postJson('/api/v1/articles', publicArticlePayload('news', $cat->id));

    $response->assertCreated();
    expect($response->json('data.status'))->toBe('draft');
    expect($response->json('data.type'))->toBe('news');
    expect(Article::where('author_id', $user->id)->where('type', 'news')->where('status', 'draft')->exists())
        ->toBeTrue();
});

// ─── 2. Writer يُنشئ Opinion Draft ────────────────────────────────────────
it('lets a writer create an opinion draft', function (): void {
    [$user, $token] = publicWriterToken();
    $cat = categoryForScope('opinion');

    $response = $this->withToken($token)
        ->postJson('/api/v1/articles', publicArticlePayload('opinion', $cat->id));

    $response->assertCreated();
    expect($response->json('data.status'))->toBe('draft');
    expect($response->json('data.type'))->toBe('opinion');
});

// ─── 3. Writer لا يُنشئ Live ──────────────────────────────────────────────
it('forbids a writer from creating live coverage', function (): void {
    [, $token] = publicWriterToken();
    $cat = categoryForScope('news');

    // النوع live يُرفض في الـ FormRequest (Rule::in[news,opinion]) → 422
    $this->withToken($token)
        ->postJson('/api/v1/articles', publicArticlePayload('live', $cat->id))
        ->assertStatus(422);
});

// ─── 4. User غير Writer → 403 ─────────────────────────────────────────────
it('returns 403 for a non-writer user', function (): void {
    $u = User::factory()->create(['is_writer' => false]);
    $u->assignRole('user');
    $token = $u->createToken('public', ['user'])->plainTextToken;
    $cat = categoryForScope('news');

    $this->withToken($token)
        ->postJson('/api/v1/articles', publicArticlePayload('news', $cat->id))
        ->assertStatus(403);
});

// ─── 5. Writer لا يُمرّر author_id ────────────────────────────────────────
it('ignores a spoofed author_id and self-assigns the writer', function (): void {
    [$writer, $token] = publicWriterToken();
    $victim = User::factory()->create();
    $cat = categoryForScope('news');

    $response = $this->withToken($token)
        ->postJson('/api/v1/articles', publicArticlePayload('news', $cat->id, ['author_id' => $victim->id]));

    $response->assertCreated();
    // الإسناد ذاتي للكاتب — لا يُنسب للضحية.
    $article = Article::latest('id')->first();
    expect($article->author_id)->toBe($writer->id);
    expect($article->author_id)->not->toBe($victim->id);
});

// ─── 6. Writer لا يُمرّر Editorial Flags ──────────────────────────────────
it('does not let a writer set editorial flags', function (): void {
    [, $token] = publicWriterToken();
    $cat = categoryForScope('news');

    $response = $this->withToken($token)
        ->postJson('/api/v1/articles', publicArticlePayload('news', $cat->id, [
            'is_featured' => true,
            'is_breaking' => true,
            'is_pinned' => true,
            'is_header' => true,
            'is_editor_pick' => true,
            'comments_enabled' => true,
        ]));

    $response->assertCreated();
    $article = Article::latest('id')->first();
    expect($article->is_featured)->toBeFalse();
    expect($article->is_breaking)->toBeFalse();
    expect($article->is_pinned)->toBeFalse();
    expect($article->is_header)->toBeFalse();
    expect($article->is_editor_pick)->toBeFalse();
});

// ─── 7. Writer لا يربط وسائط لا يملكها (OwnedMediaAsset → 422) ─────────────
// Slice 1: media/og_image_id أصبحا مقبولَين، لكن عبر حارس الملكيّة فقط — أصل
// غير موجود/غير مملوك يُرفَض بدل أن يُتجاهَل بصمت.
it('rejects binding media or og_image_id the writer does not own', function (): void {
    [, $token] = publicWriterToken();
    $cat = categoryForScope('news');

    $this->withToken($token)
        ->postJson('/api/v1/articles', publicArticlePayload('news', $cat->id, [
            'og_image_id' => 99999,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('og_image_id');

    $this->withToken($token)
        ->postJson('/api/v1/articles', publicArticlePayload('news', $cat->id, [
            'media' => [['asset_id' => 99999, 'collection' => 'cover']],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('media.0.asset_id');
});

// ─── 8. Writer: draft → submitted ────────────────────────────────────────
it('lets a writer submit their own draft for review', function (): void {
    [$writer, $token] = publicWriterToken();
    $cat = categoryForScope('news');
    $article = Article::create([
        'author_id' => $writer->id,
        'primary_category_id' => $cat->id,
        'type' => 'news',
        'status' => 'draft',
        'locale' => 'ar',
        'title' => 'مسودّتي',
        'content_json' => tiptapDoc(),
        'content' => '<p>محتوى</p>',
    ]);

    $response = $this->withToken($token)
        ->patchJson("/api/v1/articles/{$article->id}/status", ['status' => 'submitted']);

    $response->assertOk();
    expect($article->fresh()->status->value)->toBe('submitted');
});

// ─── 9. Writer لا يستطيع draft → published ────────────────────────────────
it('forbids a writer from publishing directly', function (): void {
    [$writer, $token] = publicWriterToken();
    $cat = categoryForScope('news');
    $article = Article::create([
        'author_id' => $writer->id,
        'primary_category_id' => $cat->id,
        'type' => 'news',
        'status' => 'draft',
        'locale' => 'ar',
        'title' => 'مسودّتي',
        'content_json' => tiptapDoc(),
        'content' => '<p>محتوى</p>',
    ]);

    // status=published يُرفض في الـ FormRequest (submitted فقط) → 422
    $this->withToken($token)
        ->patchJson("/api/v1/articles/{$article->id}/status", ['status' => 'published'])
        ->assertStatus(422);

    expect($article->fresh()->status->value)->toBe('draft');
});

// ─── 10. Writer لا يُرسل مقالاً لا يملكه ──────────────────────────────────
it('forbids a writer from submitting an article they do not own', function (): void {
    [, $token] = publicWriterToken();
    $other = User::factory()->create(['is_writer' => true]);
    $cat = categoryForScope('news');
    $article = Article::create([
        'author_id' => $other->id,
        'primary_category_id' => $cat->id,
        'type' => 'news',
        'status' => 'draft',
        'locale' => 'ar',
        'title' => 'مقال غيري',
        'content_json' => tiptapDoc(),
        'content' => '<p>محتوى</p>',
    ]);

    $this->withToken($token)
        ->patchJson("/api/v1/articles/{$article->id}/status", ['status' => 'submitted'])
        ->assertStatus(403);

    expect($article->fresh()->status->value)->toBe('draft');
});

// ─── 11. التدفّق الجديد: إنشاء ثمّ إرسال فوريّ للمراجعة (لا حفظ كمسودّة) ────
// يطابق ما ينفّذه createArticleAction في الواجهة: POST /articles ثمّ PATCH status=submitted.
it('creates then immediately submits the article for review', function (): void {
    [$writer, $token] = publicWriterToken();
    $cat = categoryForScope('news');

    $create = $this->withToken($token)->postJson('/api/v1/articles', publicArticlePayload('news', $cat->id));
    $create->assertCreated();
    expect($create->json('data.status'))->toBe('draft'); // الخادم يُنشئ مسودّةً إجباريّاً
    $id = $create->json('data.id');

    $submit = $this->withToken($token)->patchJson("/api/v1/articles/{$id}/status", ['status' => 'submitted']);
    $submit->assertOk();

    $article = Article::find($id);
    expect($article->status->value)->toBe('submitted');
    expect($article->author_id)->toBe($writer->id);
});
