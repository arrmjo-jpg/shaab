<?php

declare(strict_types=1);

use App\Models\Epaper;
use App\Models\MediaAsset;
use App\Models\User;
use App\Settings\MediaStorageSettings;
use App\Settings\NewspaperSettings;
use App\Support\Media\RemoteStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    $s = app(NewspaperSettings::class);
    $s->enabled = true;
    $s->save();
});

function edExternalAsset(): MediaAsset
{
    return MediaAsset::create([
        'uuid' => (string) Str::uuid(),
        'kind' => 'external',
        'disk' => 'external',
        'path' => '',
        'filename' => '',
        'original_name' => 'issue.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size' => 1024,
        'checksum' => hash('sha256', Str::random()),
        'provider' => 'external',
        'source_url' => 'https://cdn.allowed.test/issue.pdf',
        'visibility' => 'public',
    ]);
}

function edIssue(array $attrs = []): Epaper
{
    static $n = 0;
    $n++;

    return Epaper::create(array_merge([
        'locale' => 'ar',
        'issue_number' => 800 + $n,
        'title' => 'doc '.$n,
        'slug' => 'doc-'.$n,
        'publication_date' => now()->subDay()->toDateString(),
        'status' => 'published',
        'published_at' => now()->subDay(),
        'media_asset_id' => edExternalAsset()->id,
    ], $attrs));
}

function edAdmin(): User
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u;
}

// ─── View mint endpoint ──────────────────────────────────────────────────────

it('mints a short-lived signed view url (JSON) for a public issue to a guest', function (): void {
    $e = edIssue(['access_level' => 'public']);

    $res = $this->getJson($e->canonicalPath().'/document')->assertOk();

    expect($res->json('url'))->toBeString()->toContain('signature='); // app-stream fallback (no R2 in tests)
    expect($res->json('expires_at'))->toBeString();
});

it('redirects (302) the document endpoint for a no-JS open', function (): void {
    $e = edIssue(['access_level' => 'public']);

    $this->get($e->canonicalPath().'/document')->assertRedirect();
});

it('403s the document mint for a subscriber issue to a guest', function (): void {
    $e = edIssue(['access_level' => 'subscriber']);

    $this->getJson($e->canonicalPath().'/document')->assertStatus(403);
});

it('404s the document mint for a private issue to a guest', function (): void {
    $e = edIssue(['access_level' => 'private']);

    $this->getJson($e->canonicalPath().'/document')->assertNotFound();
});

it('404s delivery endpoints when the module is disabled', function (): void {
    $s = app(NewspaperSettings::class);
    $s->enabled = false;
    $s->save();
    $e = edIssue(['access_level' => 'public']);

    $this->getJson($e->canonicalPath().'/document')->assertNotFound();
});

// ─── Download (server-enforced entitlement) ──────────────────────────────────

it('403s download for a guest on a public issue (entitlement, not UI hiding)', function (): void {
    $e = edIssue(['access_level' => 'public']);

    $this->get($e->canonicalPath().'/download')->assertStatus(403);
});

it('redirects download for an admin (server-enforced)', function (): void {
    $e = edIssue(['access_level' => 'public']);

    $this->actingAs(edAdmin())->get($e->canonicalPath().'/download')->assertRedirect();
});

it('404s download for a private issue to a guest', function (): void {
    $e = edIssue(['access_level' => 'private']);

    $this->get($e->canonicalPath().'/download')->assertNotFound();
});

// ─── Emergency app-stream fallback (signed, Range-capable) ───────────────────

it('streams the pdf for a valid signature and 403s an unsigned request', function (): void {
    Storage::fake('uploads');
    $asset = MediaAsset::create([
        'uuid' => (string) Str::uuid(),
        'kind' => 'document',
        'disk' => 'uploads',
        'path' => 'epapers/stream-test.pdf',
        'filename' => 'stream-test.pdf',
        'original_name' => 'issue.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size' => 16,
        'checksum' => hash('sha256', Str::random()),
        'provider' => 'local',
        'visibility' => 'public',
    ]);
    Storage::disk('uploads')->put('epapers/stream-test.pdf', "%PDF-1.4\nstream");
    $e = edIssue(['media_asset_id' => $asset->id, 'slug' => 'streamed']);

    $signed = URL::temporarySignedRoute('epaper.document.stream', now()->addMinutes(15), [
        'epaper' => $e->id,
        'disposition' => 'inline',
    ]);

    $res = $this->get($signed)->assertOk();
    expect($res->headers->get('content-type'))->toContain('pdf');

    // unsigned / tampered → rejected
    $this->get(route('epaper.document.stream', ['epaper' => $e->id, 'disposition' => 'inline']))
        ->assertStatus(403);
});

it('falls back to app-stream when remote is enabled but the object is not yet mirrored', function (): void {
    $ms = app(MediaStorageSettings::class);
    $ms->remote_enabled = true;
    $ms->save();
    Storage::fake(RemoteStorage::diskName()); // remote configured but EMPTY (mirror not complete)

    $asset = MediaAsset::create([
        'uuid' => (string) Str::uuid(),
        'kind' => 'document',
        'disk' => 'uploads',
        'path' => 'epapers/not-mirrored.pdf',
        'filename' => 'not-mirrored.pdf',
        'original_name' => 'issue.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size' => 16,
        'checksum' => hash('sha256', Str::random()),
        'provider' => 'local',
        'visibility' => 'public',
    ]);
    $e = edIssue(['media_asset_id' => $asset->id, 'slug' => 'not-mirrored', 'access_level' => 'public']);

    $res = $this->getJson($e->canonicalPath().'/document')->assertOk();

    // الكائن غير موجود على البعيد ⇒ يرتدّ لمسار البثّ الموقَّع (لا رابط presigned).
    expect($res->json('url'))->toContain('epaper/stream');
});
