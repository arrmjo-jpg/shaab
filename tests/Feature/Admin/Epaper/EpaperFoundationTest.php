<?php

declare(strict_types=1);

use App\Actions\Admin\Media\StoreMediaAssetAction;
use App\Enums\EpaperStatus;
use App\Models\Epaper;
use App\Models\EpaperUrlHistory;
use App\Models\EpaperVersion;
use App\Models\MediaAsset;
use App\Models\User;
use App\Settings\NewspaperSettings;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function epaperPdf(): UploadedFile
{
    $body = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
        ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\n"
        ."trailer<</Root 1 0 R>>\n%%EOF";
    $path = sys_get_temp_dir().'/epaper-'.uniqid().'.pdf';
    file_put_contents($path, $body);

    return new UploadedFile($path, 'issue.pdf', 'application/pdf', null, true);
}

// ─── RBAC ─────────────────────────────────────────────────────────────────────

it('seeds the epaper permission group and grants it to super_admin', function (): void {
    foreach (['epapers.view', 'epapers.create', 'epapers.edit', 'epapers.publish', 'epapers.archive', 'epapers.delete', 'epapers.restore', 'epapers.force_delete'] as $perm) {
        expect(Permission::query()->where('name', $perm)->exists())->toBeTrue();
    }

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    expect($admin->can('epapers.publish'))->toBeTrue();

    $editor = User::factory()->create();
    $editor->assignRole('editor');
    expect($editor->can('epapers.publish'))->toBeFalse();
});

// ─── Site toggle setting ───────────────────────────────────────────────────────

it('defaults the newspaper site toggle to disabled and persists when enabled', function (): void {
    expect(app(NewspaperSettings::class)->enabled)->toBeFalse();

    $settings = app(NewspaperSettings::class);
    $settings->enabled = true;
    $settings->save();

    expect(app(NewspaperSettings::class)->refresh()->enabled)->toBeTrue();
});

// ─── PDF media support ─────────────────────────────────────────────────────────

it('stores a PDF through the media pipeline as a raw asset (no conversion/transcode)', function (): void {
    Storage::fake('uploads');
    Queue::fake();
    config(['media-library.disk_name' => 'uploads']);

    $actor = User::factory()->create();
    $asset = (new StoreMediaAssetAction)->handle(epaperPdf(), $actor);

    expect($asset)->toBeInstanceOf(MediaAsset::class);
    expect($asset->mime_type)->toBe('application/pdf');
    expect($asset->processing_status)->toBeNull();      // not image/video → no processing
    Storage::disk('uploads')->assertExists($asset->path);
});

// ─── Issue aggregate ────────────────────────────────────────────────────────────

it('creates an issue with an auto Arabic slug, status cast, soft delete and relations', function (): void {
    $epaper = Epaper::create([
        'locale' => 'ar',
        'issue_number' => 1,
        'title' => 'العدد الأول',
        'subtitle' => 'عنوان فرعي',
        'summary' => 'موجز العدد',
        'publication_date' => now()->toDateString(),
    ]);

    expect($epaper->uuid)->not->toBeEmpty();
    expect($epaper->slug)->not->toBeEmpty();
    expect($epaper->status)->toBe(EpaperStatus::Draft);    // default + enum cast
    expect($epaper->current_version)->toBe(1);
    expect($epaper->canonicalPath())->toBe("/ar/epaper/{$epaper->id}-{$epaper->slug}");

    // versioning + url history relations
    EpaperVersion::create(['epaper_id' => $epaper->id, 'version' => 1, 'note' => 'الرفع الأول']);
    EpaperUrlHistory::create(['epaper_id' => $epaper->id, 'locale' => 'ar', 'old_path' => '/ar/epaper/old']);
    expect($epaper->versions()->count())->toBe(1);
    expect($epaper->urlHistory()->count())->toBe(1);

    // soft delete
    $epaper->delete();
    expect(Epaper::query()->count())->toBe(0);
    expect(Epaper::withTrashed()->count())->toBe(1);
});

it('enforces unique issue number and slug per locale', function (): void {
    Epaper::create(['locale' => 'ar', 'issue_number' => 5, 'title' => 'عدد', 'slug' => 'issue-5', 'publication_date' => now()->toDateString()]);

    expect(fn () => Epaper::create(['locale' => 'ar', 'issue_number' => 5, 'title' => 'آخر', 'slug' => 'issue-x', 'publication_date' => now()->toDateString()]))
        ->toThrow(QueryException::class);
});

it('scopes published issues by status and publish time', function (): void {
    $live = Epaper::create(['locale' => 'ar', 'issue_number' => 10, 'title' => 'منشور', 'publication_date' => now()->toDateString(), 'status' => 'published', 'published_at' => now()->subDay()]);
    $future = Epaper::create(['locale' => 'ar', 'issue_number' => 11, 'title' => 'مجدوَل', 'publication_date' => now()->toDateString(), 'status' => 'published', 'published_at' => now()->addDay()]);
    Epaper::create(['locale' => 'ar', 'issue_number' => 12, 'title' => 'مسودة', 'publication_date' => now()->toDateString()]);

    $ids = Epaper::query()->published()->pluck('id')->all();
    expect($ids)->toContain($live->id);
    expect($ids)->not->toContain($future->id);
});
