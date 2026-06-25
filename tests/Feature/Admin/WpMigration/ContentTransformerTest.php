<?php

declare(strict_types=1);

use App\Support\Content\HtmlToTipTap;
use App\Support\Content\TipTapRenderer;
use App\Support\Content\TipTapSanitizer;

function tx(string $html): array
{
    return HtmlToTipTap::transform($html);
}

it('strips Gutenberg comments and keeps the paragraph', function (): void {
    $doc = tx('<!-- wp:paragraph --><p>نصّ عربي</p><!-- /wp:paragraph -->')['doc'];

    expect($doc['type'])->toBe('doc');
    expect($doc['content'])->toHaveCount(1);
    expect($doc['content'][0]['type'])->toBe('paragraph');
    expect($doc['content'][0]['content'][0])->toMatchArray(['type' => 'text', 'text' => 'نصّ عربي']);
});

it('preserves headings with level', function (): void {
    $doc = tx('<h2>عنوان</h2>')['doc'];

    expect($doc['content'][0]['type'])->toBe('heading');
    expect($doc['content'][0]['attrs']['level'])->toBe(2);
});

it('preserves inline marks and safe links', function (): void {
    $doc = tx('<p>عادي <strong>غامق</strong> <a href="https://shaab.test/x">رابط</a></p>')['doc'];
    $content = collect($doc['content'][0]['content']);

    expect($content->firstWhere('text', 'غامق')['marks'][0]['type'])->toBe('bold');
    $link = $content->firstWhere('text', 'رابط');
    expect($link['marks'][0]['type'])->toBe('link');
    expect($link['marks'][0]['attrs']['href'])->toBe('https://shaab.test/x');
});

it('preserves lists, blockquotes and tables', function (): void {
    $list = tx('<ul><li>أ</li><li>ب</li></ul>')['doc']['content'][0];
    expect($list['type'])->toBe('bulletList');
    expect($list['content'])->toHaveCount(2);
    expect($list['content'][0]['type'])->toBe('listItem');

    $bq = tx('<blockquote><p>اقتباس</p></blockquote>')['doc']['content'][0];
    expect($bq['type'])->toBe('blockquote');
    expect($bq['content'][0]['type'])->toBe('paragraph');

    $table = tx('<table><tbody><tr><th>ر</th><td>خ</td></tr></tbody></table>')['doc']['content'][0];
    expect($table['type'])->toBe('table');
    expect($table['content'][0]['type'])->toBe('tableRow');
    expect($table['content'][0]['content'][0]['type'])->toBe('tableHeader');
    expect($table['content'][0]['content'][1]['type'])->toBe('tableCell');
});

it('preserves figures (image + caption) and keeps original image src', function (): void {
    $r = tx('<figure><img src="https://e.test/p.jpg" alt="صورة"><figcaption>تعليق</figcaption></figure>');

    expect($r['doc']['content'][0]['type'])->toBe('image');
    expect($r['doc']['content'][0]['attrs']['src'])->toBe('https://e.test/p.jpg');
    expect($r['doc']['content'][0]['attrs']['alt'])->toBe('صورة');
    expect($r['doc']['content'][1]['type'])->toBe('paragraph'); // caption preserved
    expect($r['images'])->toContain('https://e.test/p.jpg');
});

it('maps compatible iframes to embed nodes', function (): void {
    $doc = tx('<iframe src="https://www.youtube.com/embed/abc123"></iframe>')['doc'];

    expect($doc['content'][0]['type'])->toBe('embed');
    expect($doc['content'][0]['attrs']['provider'])->toBe('youtube');
    expect($doc['content'][0]['attrs']['embed_url'])->toBe('https://www.youtube.com/embed/abc123');
});

it('preserves incompatible embeds as links (never silently dropped)', function (): void {
    $r = tx('<iframe src="https://maps.example.com/x"></iframe>');

    expect($r['doc']['content'][0]['type'])->toBe('paragraph');
    expect($r['warnings'])->toContain('embed_as_link');
});

it('unwraps containers and auto-paragraphs loose classic text', function (): void {
    $doc = tx('<div><p>داخل</p></div>')['doc'];
    expect($doc['content'][0]['type'])->toBe('paragraph');
    expect($doc['content'][0]['content'][0]['text'])->toBe('داخل');

    $loose = tx('نصّ كلاسيكي بلا وسوم')['doc'];
    expect($loose['content'][0]['type'])->toBe('paragraph');
    expect($loose['content'][0]['content'][0]['text'])->toBe('نصّ كلاسيكي بلا وسوم');
});

it('produces output that passes the real TipTap sanitizer and renderer', function (): void {
    $html = '<h2>عنوان</h2><p>فقرة <strong>غامقة</strong></p>'
        .'<blockquote><p>اقتباس</p></blockquote>'
        .'<ul><li>عنصر</li></ul>'
        .'<table><tr><td>خلية</td></tr></table>'
        .'<figure><img src="https://e.test/i.jpg" alt="ص"></figure>'
        .'<iframe src="https://youtu.be/xyz"></iframe>';

    $doc = tx($html)['doc'];

    expect(TipTapSanitizer::validate($doc))->toBeTrue();

    $out = TipTapRenderer::toHtml($doc);
    expect($out)->toContain('<h2');
    expect($out)->toContain('<blockquote>');
    expect($out)->toContain('<table>');
    expect($out)->toContain('<strong>غامقة</strong>');
    expect($out)->toContain('data-embed-provider="youtube"');
});
