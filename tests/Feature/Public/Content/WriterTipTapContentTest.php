<?php

declare(strict_types=1);

use App\Support\Content\TipTapSanitizer;

// عقد المحرّر↔الخادم: مستند يماثل ما يُخرجه محرّر TipTap في الواجهة يجب أن يجتاز
// قائمة السماح الخادميّة (وإلا رُفِض الإنشاء بـ422).
it('accepts a representative TipTap editor document', function (): void {
    $doc = [
        'type' => 'doc',
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2, 'textAlign' => 'center'], 'content' => [['type' => 'text', 'text' => 'عنوان']]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'نصّ '],
                ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'عريض'],
                ['type' => 'text', 'marks' => [['type' => 'italic']], 'text' => ' مائل'],
                ['type' => 'text', 'marks' => [['type' => 'underline']], 'text' => ' تحته خط'],
                [
                    'type' => 'text',
                    'marks' => [['type' => 'link', 'attrs' => [
                        'href' => 'https://example.com',
                        'target' => '_blank',
                        'rel' => 'noopener noreferrer nofollow',
                        'class' => null,
                    ]]],
                    'text' => 'رابط',
                ],
            ]],
            ['type' => 'bulletList', 'content' => [
                ['type' => 'listItem', 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'عنصر']]],
                ]],
            ]],
            ['type' => 'orderedList', 'attrs' => ['start' => 1], 'content' => [
                ['type' => 'listItem', 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'أوّل']]],
                ]],
            ]],
            ['type' => 'blockquote', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'اقتباس']]],
            ]],
            // صورة داخل المحتوى — رابط مطلق من طبقة وسائط الكاتب.
            ['type' => 'image', 'attrs' => [
                'src' => 'http://localhost:8000/uploads/assets/abc/abc.jpg',
                'alt' => 'صورة',
                'title' => null,
            ]],
            // فيديو يوتيوب مضمّن — عقدة embed (لا رابط) كما يُنتجها اللصق الذكيّ.
            ['type' => 'embed', 'attrs' => [
                'provider' => 'youtube',
                'embed_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0&modestbranding=1',
                'id' => 'dQw4w9WgXcQ',
            ]],
            ['type' => 'horizontalRule'],
        ],
    ];

    expect(TipTapSanitizer::validate($doc))->toBeTrue();
});

// حارس: تضمين بمزوّد خارج قائمة EmbedProvider يُرفَض.
it('rejects an embed with a disallowed provider', function (): void {
    $doc = [
        'type' => 'doc',
        'content' => [
            ['type' => 'embed', 'attrs' => ['provider' => 'tiktok', 'embed_url' => 'https://www.tiktok.com/embed/v2/123']],
        ],
    ];

    expect(TipTapSanitizer::validate($doc))->toBeFalse();
});

// حارس أمنيّ: صورة برابط غير http(s) (مثل javascript:) تُرفَض.
it('rejects an inline image with an unsafe src scheme', function (): void {
    $doc = [
        'type' => 'doc',
        'content' => [
            ['type' => 'image', 'attrs' => ['src' => 'javascript:alert(1)']],
        ],
    ];

    expect(TipTapSanitizer::validate($doc))->toBeFalse();
});
