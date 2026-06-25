<?php

declare(strict_types=1);

use App\Support\WpMigration\WpMediaResolver;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/wpmig-'.uniqid();
    @mkdir($this->root.'/2024/01', 0777, true);
    @mkdir($this->root.'/2024/02', 0777, true);
    @mkdir($this->root.'/2024/04', 0777, true);
    file_put_contents($this->root.'/2024/01/photo.jpg', 'x');          // أصلية
    file_put_contents($this->root.'/2024/01/photo-300x200.jpg', 'x');  // مشتقّ
    file_put_contents($this->root.'/2024/02/pic-150x150.jpg', 'x');    // مشتقّ صغير (لا أصل)
    file_put_contents($this->root.'/2024/02/pic-1024x768.jpg', 'x');   // مشتقّ كبير
    file_put_contents($this->root.'/2024/04/doc-scaled.jpg', 'x');     // scaled فقط
});

afterEach(function (): void {
    if (! is_dir($this->root)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($this->root);
});

it('prefers the original over a size derivative', function (): void {
    $res = (new WpMediaResolver($this->root))
        ->resolve('https://shaab.test/wp-content/uploads/2024/01/photo-300x200.jpg');

    expect($res->isLocal())->toBeTrue();
    expect(basename((string) $res->path))->toBe('photo.jpg');
});

it('falls back to the largest derivative when the original is missing', function (): void {
    $res = (new WpMediaResolver($this->root))
        ->resolve('https://shaab.test/wp-content/uploads/2024/02/pic-150x150.jpg');

    expect($res->isLocal())->toBeTrue();
    expect(basename((string) $res->path))->toBe('pic-1024x768.jpg');
});

it('uses the referenced file as last resort (scaled, no WxH siblings)', function (): void {
    $res = (new WpMediaResolver($this->root))
        ->resolve('https://shaab.test/wp-content/uploads/2024/04/doc-scaled.jpg');

    expect($res->isLocal())->toBeTrue();
    expect(basename((string) $res->path))->toBe('doc-scaled.jpg');
});

it('classifies non-uploads http urls as external', function (): void {
    $res = (new WpMediaResolver($this->root))->resolve('https://cdn.other.test/a/b.jpg');

    expect($res->isExternal())->toBeTrue();
    expect($res->url)->toBe('https://cdn.other.test/a/b.jpg');
});

it('blocks path traversal outside the uploads root', function (): void {
    $res = (new WpMediaResolver($this->root))
        ->resolve('https://shaab.test/wp-content/uploads/../../../../Windows/win.ini');

    expect($res->isUnresolved())->toBeTrue();
});

it('returns unresolved for a missing upload', function (): void {
    $res = (new WpMediaResolver($this->root))
        ->resolve('https://shaab.test/wp-content/uploads/2024/01/nope.jpg');

    expect($res->isUnresolved())->toBeTrue();
});
