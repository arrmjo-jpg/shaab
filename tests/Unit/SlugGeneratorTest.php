<?php

declare(strict_types=1);

use App\Support\Content\SlugGenerator;

it('normalizes punctuation, separators and special characters', function (string $input, string $expected): void {
    expect(SlugGenerator::make($input))->toBe($expected);
})->with([
    'plain arabic' => ['عنوان المقال', 'عنوان-المقال'],
    'latin lowercased' => ['Local Politics', 'local-politics'],
    'collapses repeated separators' => ['a  -  b', 'a-b'],
    'strips punctuation' => ['خبر: عاجل!! (مهم)', 'خبر-عاجل-مهم'],
    'trims edge separators' => ['  --hello--  ', 'hello'],
    'mixed arabic + numbers' => ['تقرير 2026 السنوي', 'تقرير-2026-السنوي'],
    'special chars dropped' => ['news@#$%^&*()title', 'newstitle'],
    'all punctuation → empty' => ['!!!???', ''],
]);

it('falls back to a non-empty slug when normalization yields empty', function (): void {
    expect(SlugGenerator::makeWithFallback('!!!'))->not->toBe('');
    expect(SlugGenerator::makeWithFallback('عنوان'))->toBe('عنوان');
});
