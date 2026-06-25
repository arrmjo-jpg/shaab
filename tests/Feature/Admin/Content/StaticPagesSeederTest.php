<?php

declare(strict_types=1);

use App\Models\Page;
use Database\Seeders\StaticPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the five standard footer pages, published + idempotent', function (): void {
    $this->seed(StaticPagesSeeder::class);

    $slugs = ['about-us', 'privacy-policy', 'usage-policy', 'terms', 'advertise'];

    expect(Page::count())->toBe(5);
    foreach ($slugs as $slug) {
        $page = Page::where('slug', $slug)->first();
        expect($page)->not->toBeNull();
        expect($page->status->value)->toBe('published');
        expect($page->show_in_footer)->toBeTrue();
        expect($page->published_at)->not->toBeNull();
    }

    // إعادة التشغيل لا تُكرّر (idempotent).
    $this->seed(StaticPagesSeeder::class);
    expect(Page::count())->toBe(5);
});
