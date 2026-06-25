<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Support\Content\TipTapSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function p4Token(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin-token', ['admin'])->plainTextToken;
}

function p4Cat(): Category
{
    return Category::create([
        'name' => 'تصنيف '.uniqid(), 'locale' => 'ar', 'status' => 'active', 'scope' => 'both',
    ]);
}

function p4Payload(Category $c, array $doc): array
{
    return [
        'title' => 'عنوان', 'locale' => 'ar', 'type' => 'news',
        'primary_category_id' => $c->id, 'excerpt' => 'ملخّص.', 'content_json' => $doc,
    ];
}

// ─── Canonical JSON storage + derived HTML ─────────────────────────────

it('stores TipTap JSON canonically and derives sanitized HTML', function (): void {
    $token = p4Token();
    $doc = [
        'type' => 'doc',
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [
                ['type' => 'text', 'text' => 'العنوان'],
            ]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'نص ', 'marks' => [['type' => 'bold']]],
                ['type' => 'text', 'text' => 'رابط', 'marks' => [
                    ['type' => 'link', 'attrs' => ['href' => 'https://example.com']],
                ]],
            ]],
        ],
    ];

    $res = $this->withToken($token)->postJson('/api/v1/admin/articles', p4Payload(p4Cat(), $doc))
        ->assertCreated();

    $a = Article::find($res->json('data.id'));
    expect($a->content_json)->toBeArray();
    expect($a->content_json['type'])->toBe('doc');
    expect($a->content)->toContain('<h2>')->toContain('<strong>');
    // الرابط مُطبَّع برابط آمن + rel/target
    expect($a->content)->toContain('rel="noopener noreferrer nofollow"');
});

// ─── Allow-list rejections ─────────────────────────────────────────────

it('rejects an unknown node type', function (): void {
    $token = p4Token();
    $doc = ['type' => 'doc', 'content' => [['type' => 'iframe', 'attrs' => ['src' => 'https://evil']]]];

    $this->withToken($token)->postJson('/api/v1/admin/articles', p4Payload(p4Cat(), $doc))
        ->assertStatus(422)->assertJsonValidationErrors(['content_json']);
});

it('rejects a javascript: link href', function (): void {
    $token = p4Token();
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'x', 'marks' => [
                ['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']],
            ]],
        ]],
    ]];

    $this->withToken($token)->postJson('/api/v1/admin/articles', p4Payload(p4Cat(), $doc))
        ->assertStatus(422);
});

it('rejects a raw html node', function (): void {
    $token = p4Token();
    $doc = ['type' => 'doc', 'content' => [['type' => 'html', 'attrs' => ['html' => '<script>x</script>']]]];

    $this->withToken($token)->postJson('/api/v1/admin/articles', p4Payload(p4Cat(), $doc))
        ->assertStatus(422);
});

it('content_json is required (legacy content string not accepted)', function (): void {
    $token = p4Token();

    $this->withToken($token)->postJson('/api/v1/admin/articles', [
        'title' => 'ع', 'locale' => 'ar', 'type' => 'news',
        'primary_category_id' => p4Cat()->id, 'content' => '<p>raw</p>',
    ])->assertStatus(422)->assertJsonValidationErrors(['content_json']);
});

// ─── Typed embeds (no raw iframe) ──────────────────────────────────────

it('accepts a typed embed node from an allow-listed provider', function (): void {
    $token = p4Token();
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'embed', 'attrs' => [
            'provider' => 'youtube',
            'embed_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'id' => 'dQw4w9WgXcQ',
        ]],
    ]];

    $res = $this->withToken($token)->postJson('/api/v1/admin/articles', p4Payload(p4Cat(), $doc))
        ->assertCreated();

    expect(Article::find($res->json('data.id'))->content)
        ->toContain('data-embed-provider="youtube"');
});

it('rejects an embed with a non-allow-listed provider', function (): void {
    $token = p4Token();
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'embed', 'attrs' => ['provider' => 'tiktok', 'embed_url' => 'https://tiktok.com/x']],
    ]];

    $this->withToken($token)->postJson('/api/v1/admin/articles', p4Payload(p4Cat(), $doc))
        ->assertStatus(422);
});

// ─── Poll embedding (Phase 3) ──────────────────────────────────────────

it('accepts a poll node and renders a uuid-only placeholder', function (): void {
    $token = p4Token();
    $uuid = (string) Str::uuid();
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'poll', 'attrs' => ['uuid' => $uuid]],
    ]];

    $res = $this->withToken($token)->postJson('/api/v1/admin/articles', p4Payload(p4Cat(), $doc))
        ->assertCreated();

    expect(Article::find($res->json('data.id'))->content)
        ->toContain('data-poll-uuid="'.$uuid.'"');
});

it('rejects a poll node with a malformed uuid', function (): void {
    $token = p4Token();
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'poll', 'attrs' => ['uuid' => 'not-a-uuid']],
    ]];

    $this->withToken($token)->postJson('/api/v1/admin/articles', p4Payload(p4Cat(), $doc))
        ->assertStatus(422)->assertJsonValidationErrors(['content_json']);
});

it('strips extra attributes from a poll node (keeps only uuid)', function (): void {
    $uuid = (string) Str::uuid();
    $clean = TipTapSanitizer::clean(['type' => 'doc', 'content' => [
        ['type' => 'poll', 'attrs' => ['uuid' => $uuid, 'evil' => '<x>', 'extra' => 1]],
    ]]);

    expect($clean['content'][0]['attrs'])->toBe(['uuid' => $uuid]);
});

// ─── Text alignment (justify / center / right) ─────────────────────────

it('preserves text alignment on paragraphs and headings', function (): void {
    $token = p4Token();
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'attrs' => ['textAlign' => 'justify'], 'content' => [
            ['type' => 'text', 'text' => 'فقرة مضبوطة'],
        ]],
        ['type' => 'heading', 'attrs' => ['level' => 2, 'textAlign' => 'center'], 'content' => [
            ['type' => 'text', 'text' => 'عنوان موسّط'],
        ]],
    ]];

    $a = Article::find(
        $this->withToken($token)->postJson('/api/v1/admin/articles', p4Payload(p4Cat(), $doc))
            ->assertCreated()->json('data.id')
    );

    expect($a->content_json['content'][0]['attrs']['textAlign'])->toBe('justify');
    expect($a->content)->toContain('style="text-align:justify"');
    expect($a->content)->toContain('style="text-align:center"');
});

it('strips an unknown text alignment value', function (): void {
    $token = p4Token();
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'attrs' => ['textAlign' => 'diagonal'], 'content' => [
            ['type' => 'text', 'text' => 'نص'],
        ]],
    ]];

    // قيمة غير مسموحة ⇒ رفض المستند بالكامل (allow-list صارمة).
    $this->withToken($token)->postJson('/api/v1/admin/articles', p4Payload(p4Cat(), $doc))
        ->assertStatus(422);
});

// ─── XSS posture ───────────────────────────────────────────────────────

it('escapes script-like text in the derived HTML', function (): void {
    $token = p4Token();
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => '<script>alert(1)</script>'],
        ]],
    ]];

    $res = $this->withToken($token)->postJson('/api/v1/admin/articles', p4Payload(p4Cat(), $doc))
        ->assertCreated();

    $html = Article::find($res->json('data.id'))->content;
    expect($html)->not->toContain('<script>');
    expect($html)->toContain('&lt;script&gt;');
});

// ─── Tags ──────────────────────────────────────────────────────────────

it('syncs tags and exposes them on the resource', function (): void {
    $token = p4Token();
    $payload = p4Payload(p4Cat(), tiptapDoc());
    $payload['tags'] = ['سياسة', 'اقتصاد'];

    $res = $this->withToken($token)->postJson('/api/v1/admin/articles', $payload)
        ->assertCreated();

    expect(collect($res->json('data.tags')))->toContain('سياسة', 'اقتصاد');
    expect(Article::find($res->json('data.id'))->tags()->count())->toBe(2);
});

// ─── Update re-derives ─────────────────────────────────────────────────

it('re-derives HTML when content_json is updated', function (): void {
    $token = p4Token();
    $id = $this->withToken($token)->postJson('/api/v1/admin/articles', p4Payload(p4Cat(), tiptapDoc('قديم')))
        ->json('data.id');

    $this->withToken($token)->putJson("/api/v1/admin/articles/{$id}", [
        'content_json' => tiptapDoc('جديد'),
    ])->assertOk();

    expect(Article::find($id)->content)->toContain('جديد')->not->toContain('قديم');
});

// ─── Sanitizer unit + config ───────────────────────────────────────────

it('sanitizer rejects malformed docs and strips disallowed attrs', function (): void {
    expect(TipTapSanitizer::validate(['type' => 'paragraph']))->toBeFalse();
    expect(TipTapSanitizer::validate(['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'ok']]],
    ]]))->toBeTrue();

    $clean = TipTapSanitizer::clean(['type' => 'doc', 'content' => [
        ['type' => 'heading', 'attrs' => ['level' => 3, 'evil' => 'x'], 'content' => [
            ['type' => 'text', 'text' => 't'],
        ]],
    ]]);
    expect($clean['content'][0]['attrs'])->toBe(['level' => 3]);
});

it('video upload ceiling is the locked configurable 250MB', function (): void {
    expect((int) config('performance.media.video_max_kb'))->toBe(256000);
});
