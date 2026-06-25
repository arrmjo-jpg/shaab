<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\Models\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;

/**
 * تهيئة الأدوار والصلاحيات + مسح كاش Spatie.
 * تُستدعى في الاختبارات التي تحتاج أدواراً.
 */
function seedRoles(): void
{
    test()->seed(RolesAndPermissionsSeeder::class);

    // أدوار تحريرية كـ fixtures للاختبارات فقط — سيدر الإنتاج مُقلَّم
    // عمداً (super_admin + user)، فلا نقرن الاختبارات بتصنيف الإنتاج.
    foreach (['editor', 'reviewer', 'moderator', 'social_media_manager', 'journalist', 'contributor'] as $name) {
        Role::findOrCreate($name, 'web');
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

/**
 * تأكيد أن الاستجابة تتبع عقد النجاح الموحّد.
 */
function assertSuccessContract(TestResponse $response): void
{
    $response->assertJsonStructure(['success', 'message', 'data', 'meta']);
    expect($response->json('success'))->toBeTrue();
}

/**
 * تأكيد أن الاستجابة تتبع عقد الخطأ الموحّد.
 */
function assertErrorContract(TestResponse $response): void
{
    $response->assertJsonStructure(['success', 'message', 'errors']);
    expect($response->json('success'))->toBeFalse();
}

/**
 * مستند TipTap أدنى صالح (P4-D1) — للاختبارات.
 *
 * @return array<string,mixed>
 */
function tiptapDoc(string $text = 'محتوى'): array
{
    return [
        'type' => 'doc',
        'content' => [
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => $text],
            ]],
        ],
    ];
}
