<?php

declare(strict_types=1);

use App\Support\Advertising\AdHtmlSanitizer;

// يقرأ المُنقّي config('advertising.creatives.html') ⇒ يحتاج تطبيق Laravel مُقلَعاً،
// لذا يعيش تحت Feature (Pest يربط TestCase بـ Feature فقط) لا Unit.

it('strips script, iframe, object, embed and inline event handlers', function (): void {
    $dirty = '<div onclick="evil()">keep'
        .'<script>alert(1)</script>'
        .'<iframe src="https://evil.test"></iframe>'
        .'<object data="x.swf"></object>'
        .'<embed src="x.swf">'
        .'</div>';

    $clean = AdHtmlSanitizer::sanitize($dirty);

    expect($clean)->toContain('keep')
        ->and($clean)->not->toContain('<script')
        ->and($clean)->not->toContain('<iframe')
        ->and($clean)->not->toContain('<object')
        ->and($clean)->not->toContain('<embed')
        ->and($clean)->not->toContain('onclick');
});

it('drops dangerous link schemes (javascript:/data:)', function (): void {
    $js = AdHtmlSanitizer::sanitize('<a href="javascript:alert(1)">x</a>');
    $data = AdHtmlSanitizer::sanitize('<img src="data:text/html;base64,PHN2Zz4=" alt="x">');

    expect($js)->not->toContain('javascript:')
        ->and($data)->not->toContain('data:');
});

it('keeps whitelisted tags, attributes and http(s) links', function (): void {
    $clean = AdHtmlSanitizer::sanitize(
        '<a href="https://example.com" target="_blank">go</a><strong>bold</strong><p class="lead">hi</p>'
    );

    expect($clean)->toContain('https://example.com')
        ->and($clean)->toContain('<strong>')
        ->and($clean)->toContain('go');
});

it('filters inline styles down to the allowed CSS whitelist', function (): void {
    // position غير مسموح (هروب من التخطيط)؛ color مسموح.
    $clean = AdHtmlSanitizer::sanitize('<div style="position:fixed;color:red">x</div>');

    expect($clean)->not->toContain('position')
        ->and($clean)->toContain('color');
});

it('returns an empty string for null or blank input', function (): void {
    expect(AdHtmlSanitizer::sanitize(null))->toBe('')
        ->and(AdHtmlSanitizer::sanitize('   '))->toBe('');
});
