<?php

declare(strict_types=1);

use App\Actions\Admin\Epaper\CreateEpaperAction;
use App\Actions\Admin\Epaper\ExtractEpaperTextAction;
use App\Actions\Admin\Epaper\ReplacePdfAction;
use App\Enums\EpaperOcrStatus;
use App\Enums\EpaperTextLayer;
use App\Jobs\ExtractEpaperTextJob;
use App\Models\Epaper;
use App\Models\EpaperPage;
use App\Models\MediaAsset;
use App\Models\User;
use App\Settings\NewspaperSettings;
use App\Support\Epaper\Ocr\DefaultEpaperOcrProvider;
use App\Support\Epaper\Ocr\EmbeddedPdfTextProvider;
use App\Support\Epaper\Ocr\EpaperOcrProvider;
use App\Support\Epaper\Ocr\GoogleDocumentAiProvider;
use App\Support\Epaper\Ocr\OcrExtraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Storage::fake('uploads');
    config(['media-library.disk_name' => 'uploads']);
    $s = app(NewspaperSettings::class);
    $s->enabled = true;
    $s->save();
});

/** أصل PDF محلّيّ مرفوع على القرص المُزيَّف (يصلح للتحقّق من materializePdf). */
function ocrAsset(string $body = "%PDF-1.4\ntest\n%%EOF"): MediaAsset
{
    $path = 'assets/'.uniqid().'/issue.pdf';
    Storage::disk('uploads')->put($path, $body);

    return MediaAsset::create([
        'uuid' => (string) Str::uuid(),
        'kind' => 'document',
        'disk' => 'uploads',
        'path' => $path,
        'filename' => 'issue.pdf',
        'original_name' => 'issue.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size' => strlen($body),
        'checksum' => hash('sha256', Str::random()),
        'provider' => 'local',
        'visibility' => 'public',
    ]);
}

function ocrIssue(?int $assetId = null): Epaper
{
    static $n = 0;
    $n++;

    return Epaper::create([
        'locale' => 'ar',
        'issue_number' => 5000 + $n,
        'title' => 'عدد OCR '.$n,
        'slug' => 'ocr-'.$n,
        'publication_date' => now()->subDay()->toDateString(),
        'status' => 'published',
        'published_at' => now()->subDay(),
        'media_asset_id' => $assetId,
    ]);
}

function ocrUpload(): UploadedFile
{
    $path = sys_get_temp_dir().'/ocr-'.uniqid().'.pdf';
    file_put_contents($path, "%PDF-1.4\ntest\n%%EOF");

    return new UploadedFile($path, 'issue.pdf', 'application/pdf', null, true);
}

/** يربط مزوّد OCR مُزيَّفاً يعيد نتيجة محدّدة (لاختبار الخطّ بمعزل عن الأدوات). */
function ocrBindProvider(OcrExtraction $extraction): void
{
    app()->bind(EpaperOcrProvider::class, fn (): EpaperOcrProvider => new class($extraction) implements EpaperOcrProvider
    {
        public function __construct(private readonly OcrExtraction $extraction) {}

        public function extract(string $pdfPath): OcrExtraction
        {
            return $this->extraction;
        }
    });
}

function ocrConfigureGoogle(): void
{
    // مفتاح RSA للاختبار فقط (رمي — لا سرّ) مضمَّن ثابتاً كي لا يعتمد الاختبار على
    // توليد مفاتيح زمن التشغيل (يفشل على بيئات بلا openssl.cnf).
    $pem = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC+tkV+SLrPfF5D
9weoHq72qxHua4yu8RO3ljocMQc2wT+6pJKKT2R8oh1o7hSJWLe03bwrhne0/bUn
r7SSlaASorGDBRVbQ9k3vdxNyZWkZl7sJHb3VJq2NhL7oefLyBwDpiHKZtJsjt8f
ZraRfPRehOuzUnVYQnRhpxNi4NaGDmeBSi9DcPy3S/LUcOCwei+pNMmx29164Slo
nq5bHph1S1z+2Ibg9uZXmFfdFw6QmL+FP2Nll8hysi1Xrzz35BtCcQyZC/uNqP+B
dAHkKJX6hO3wZ73CeAaSGLf/W1XiX3EsiLgGc6sVTVlVdAJ4OXRtxnADaMDO3hHj
eWKUn+OTAgMBAAECggEAXUf7Kr85PJZ45yZzpMxJSRa4wRTy7Xi2a7Q7vSFQBXy1
hr3LfYJCxOFooDPfcKSnynwwd1ugvrLfwkcjfBVag/L2/35jiU3g9+5STOv+WTjH
Uhqt4+EKgXhmhZUWMyswZKqEWaM8ZmPlh87uGrBzOK4sOXxqMB7lHQSjK1iNJWkb
DGaMPqVtPizyxviXkeOpZPR2L+kzAraVRTUUXQK9WFD+mkTYY8AEr9EvCZxAad/1
7M31xirzdg58NbZ4SP9+VPy+Oqmippm8ngwWtnm4XdN9vnGQYt3A5HWpYtkiF2el
9ISgk0jSLoaUU0Kxf7rMW0AWTPNssk4F+xR0gmUplQKBgQDhxXRO+fXDwMaV88TN
ovgWMjK7jolOwwtoo4YcnbbAD/C9QrsjRk9HmvQzCPwcN1paz7XNyjHm8+ThJcCd
Lp2adeJHY9gUJb3ZWLufYNd++/bUyucy1mTNP7cfHEtprtskqTOgr25wJE04Yql2
qwCtlUz2kBDhKlJ02tAEQ55jRQKBgQDYPx97uVFobYZnxn6e9VD5co2LTlAWHrY4
RryAGxD+OtdM5Xm5QkC5GLLU4Hk3CVXXorwm7hGcJsqAF2mllZ1hvbb/s//6vxqv
U68gdYFX6M5RezcKHTd8qMhvo2+3jWABxv2JzFFcyruXv2VsZIp8gI0r/hrTiK8V
6lSpoYls9wKBgAI+HuCl9P4DzTUyHbNZOhOmXgCk8tI4d8WLUkq4eldAEUkf/5Hj
Ieh5LpHPNgnltt0OESVBK+u6YnymDlrBWsltAFlrMXtJwLAHBJ4ZrSpSwGnutgs4
O/oZ9uy1MD6VgRHFKIEhHPy6L5YuzLYkDraqtAADAFfsPrNwdP6F2W3tAoGAcUaP
nWX0CPnmgBHwXiAvLJwfHSwGs6+e0FftgkWrXyE/it5iJvNXqB4R/4Ueuf+/4dcz
LEllHCENzo91HfIDoSGZ7NRDcPwOZG03vY8QFBa1jOU4bankWP6pECHS8ZmzAvtT
8I0AydTA87qkzGTWTmWgjbzsHIbrAFXhx4IA1P0CgYEAkD22jKF+bL0lD+uAC6oI
6Bv0b0M4NplfUbP/Uq9NQxKEQQ/74m92vJNd+F5MCoovzcOEjz0vrAlVazO17ta6
IueEz7ZrPuk3jUacC6q92am70TjHFIMVSluyy1rFRTtiVh556zbOoD8V94J4Kp6Y
DlKUNo5kbHtu28qiV4bxuzA=
-----END PRIVATE KEY-----
PEM;

    config([
        'epaper.ocr.google.enabled' => true,
        'epaper.ocr.google.project_id' => 'proj',
        'epaper.ocr.google.location' => 'us',
        'epaper.ocr.google.processor_id' => 'proc',
        'epaper.ocr.google.credentials' => json_encode([
            'client_email' => 'svc@proj.iam.gserviceaccount.com',
            'private_key' => $pem,
            'token_uri' => 'https://oauth2.test/token',
        ]),
    ]);
}

/** @param array<string,mixed> $document */
function ocrFakeGoogle(array $document): void
{
    Http::fake([
        'oauth2.test/token' => Http::response(['access_token' => 'tok-'.uniqid(), 'expires_in' => 3600]),
        '*documentai.googleapis.com/*' => Http::response(['document' => $document]),
    ]);
}

// ─── Pipeline state transitions ──────────────────────────────────────────────

it('marks ocr done/present and stores pages when every page has text', function (): void {
    $epaper = ocrIssue(ocrAsset()->id);
    ocrBindProvider(new OcrExtraction([1 => 'صفحة أولى', 2 => 'صفحة ثانية'], 'embedded'));

    app(ExtractEpaperTextAction::class)->handle($epaper->id);

    $fresh = $epaper->fresh();
    expect($fresh->ocr_status)->toBe(EpaperOcrStatus::Done);
    expect($fresh->text_layer)->toBe(EpaperTextLayer::Present);
    expect($fresh->page_count)->toBe(2);
    expect(EpaperPage::where('epaper_id', $epaper->id)->count())->toBe(2);
    expect(EpaperPage::where('epaper_id', $epaper->id)->where('page_number', 1)->value('text'))->toBe('صفحة أولى');
});

it('marks ocr partial when only some pages have text', function (): void {
    $epaper = ocrIssue(ocrAsset()->id);
    ocrBindProvider(new OcrExtraction([1 => 'نصّ', 2 => '', 3 => 'نصّ آخر'], 'embedded'));

    app(ExtractEpaperTextAction::class)->handle($epaper->id);

    $fresh = $epaper->fresh();
    expect($fresh->ocr_status)->toBe(EpaperOcrStatus::Partial);
    expect($fresh->text_layer)->toBe(EpaperTextLayer::Partial);
    expect($fresh->page_count)->toBe(3);
    expect(EpaperPage::where('epaper_id', $epaper->id)->where('has_text', true)->count())->toBe(2);
});

it('marks ocr done/absent when pages exist but none carry text (scanned)', function (): void {
    $epaper = ocrIssue(ocrAsset()->id);
    ocrBindProvider(new OcrExtraction([1 => '', 2 => ''], 'embedded'));

    app(ExtractEpaperTextAction::class)->handle($epaper->id);

    $fresh = $epaper->fresh();
    expect($fresh->ocr_status)->toBe(EpaperOcrStatus::Done);
    expect($fresh->text_layer)->toBe(EpaperTextLayer::Absent);
    expect($fresh->page_count)->toBe(2);
});

it('marks ocr failed when extraction yields no page structure', function (): void {
    $epaper = ocrIssue(ocrAsset()->id);
    ocrBindProvider(OcrExtraction::empty('embedded'));

    app(ExtractEpaperTextAction::class)->handle($epaper->id);

    expect($epaper->fresh()->ocr_status)->toBe(EpaperOcrStatus::Failed);
    expect(EpaperPage::where('epaper_id', $epaper->id)->count())->toBe(0);
});

it('marks ocr failed and does not crash when the provider throws', function (): void {
    $epaper = ocrIssue(ocrAsset()->id);
    app()->bind(EpaperOcrProvider::class, fn (): EpaperOcrProvider => new class implements EpaperOcrProvider
    {
        public function extract(string $pdfPath): OcrExtraction
        {
            throw new RuntimeException('provider boom');
        }
    });

    app(ExtractEpaperTextAction::class)->handle($epaper->id);

    expect($epaper->fresh()->ocr_status)->toBe(EpaperOcrStatus::Failed);
});

it('marks ocr failed when the issue has no local document', function (): void {
    $epaper = ocrIssue(null); // لا أصل
    ocrBindProvider(new OcrExtraction([1 => 'x'], 'embedded')); // لن يُستدعى

    app(ExtractEpaperTextAction::class)->handle($epaper->id);

    expect($epaper->fresh()->ocr_status)->toBe(EpaperOcrStatus::Failed);
});

// ─── Idempotency ─────────────────────────────────────────────────────────────

it('is idempotent — re-running yields the same pages with no duplication', function (): void {
    $epaper = ocrIssue(ocrAsset()->id);
    ocrBindProvider(new OcrExtraction([1 => 'أ', 2 => 'ب'], 'embedded'));

    app(ExtractEpaperTextAction::class)->handle($epaper->id);
    app(ExtractEpaperTextAction::class)->handle($epaper->id);

    expect(EpaperPage::where('epaper_id', $epaper->id)->count())->toBe(2);
    expect($epaper->fresh()->page_count)->toBe(2);
});

it('re-extraction overwrites stale pages (fewer pages on rerun)', function (): void {
    $epaper = ocrIssue(ocrAsset()->id);
    ocrBindProvider(new OcrExtraction([1 => 'a', 2 => 'b', 3 => 'c'], 'embedded'));
    app(ExtractEpaperTextAction::class)->handle($epaper->id);
    expect(EpaperPage::where('epaper_id', $epaper->id)->count())->toBe(3);

    ocrBindProvider(new OcrExtraction([1 => 'x'], 'embedded'));
    app(ExtractEpaperTextAction::class)->handle($epaper->id);

    expect(EpaperPage::where('epaper_id', $epaper->id)->count())->toBe(1);
    expect($epaper->fresh()->page_count)->toBe(1);
});

// ─── Dispatch hooks: create + replace requeue ────────────────────────────────

it('queues OCR as pending when an issue is created', function (): void {
    Queue::fake();
    $user = User::factory()->create();

    (new CreateEpaperAction)->handle([
        'locale' => 'ar',
        'issue_number' => 7777,
        'title' => 'عدد جديد',
        'slug' => 'ocr-new',
        'publication_date' => now()->toDateString(),
    ], ocrUpload(), $user);

    $epaper = Epaper::where('issue_number', 7777)->firstOrFail();
    expect($epaper->ocr_status)->toBe(EpaperOcrStatus::Pending);
    Queue::assertPushed(ExtractEpaperTextJob::class);
});

it('re-queues OCR (pending) and resets metadata when the PDF is replaced', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $epaper = ocrIssue(ocrAsset()->id);
    $epaper->forceFill([
        'ocr_status' => EpaperOcrStatus::Done->value,
        'text_layer' => EpaperTextLayer::Present->value,
        'page_count' => 5,
    ])->save();

    (new ReplacePdfAction)->handle($epaper, ocrUpload(), 'نسخة مصحّحة', $user);

    $fresh = $epaper->fresh();
    expect($fresh->ocr_status)->toBe(EpaperOcrStatus::Pending);
    expect($fresh->text_layer)->toBeNull();
    expect($fresh->page_count)->toBeNull();
    Queue::assertPushed(ExtractEpaperTextJob::class);
});

// ─── Admin rerun endpoint ────────────────────────────────────────────────────

it('re-queues OCR via the rerun endpoint with epapers.edit', function (): void {
    Queue::fake();
    $u = User::factory()->create();
    $u->assignRole('super_admin');
    $token = $u->createToken('admin', ['admin'])->plainTextToken;
    $epaper = ocrIssue(ocrAsset()->id);
    $epaper->forceFill(['ocr_status' => EpaperOcrStatus::Failed->value])->save();

    $this->withToken($token)->postJson("/api/v1/admin/epapers/{$epaper->id}/ocr/rerun")->assertOk();

    expect($epaper->fresh()->ocr_status)->toBe(EpaperOcrStatus::Pending);
    Queue::assertPushed(ExtractEpaperTextJob::class);
});

it('forbids the OCR rerun endpoint without epapers.edit', function (): void {
    $u = User::factory()->create(); // بلا أدوار/صلاحيات
    $token = $u->createToken('admin', ['admin'])->plainTextToken;
    $epaper = ocrIssue(ocrAsset()->id);

    $this->withToken($token)->postJson("/api/v1/admin/epapers/{$epaper->id}/ocr/rerun")->assertStatus(403);
});

// ─── Embedded provider (pdftotext) — Process faked ───────────────────────────

it('embedded provider splits pdftotext output into pages by form-feed', function (): void {
    Process::fake(['*' => Process::result(output: "Page one\fPage two\f")]);

    $extraction = (new EmbeddedPdfTextProvider)->extract('/tmp/whatever.pdf');

    expect($extraction->pageCount())->toBe(2);
    expect($extraction->pages[1])->toBe('Page one');
    expect($extraction->pages[2])->toBe('Page two');
    expect($extraction->source)->toBe('embedded');
});

it('embedded provider returns empty when pdftotext is unavailable', function (): void {
    Process::fake(['*' => Process::result(output: '', exitCode: 127)]);

    expect((new EmbeddedPdfTextProvider)->extract('/tmp/x.pdf')->pageCount())->toBe(0);
});

// ─── Google Document AI provider — Http faked ────────────────────────────────

it('google provider extracts per-page text from a Document AI response', function (): void {
    ocrConfigureGoogle();
    ocrFakeGoogle([
        'text' => 'HELLO WORLD',
        'pages' => [
            ['pageNumber' => 1, 'layout' => ['textAnchor' => ['textSegments' => [['startIndex' => '0', 'endIndex' => '5']]]]],
            ['pageNumber' => 2, 'layout' => ['textAnchor' => ['textSegments' => [['startIndex' => '6', 'endIndex' => '11']]]]],
        ],
    ]);

    $pdf = sys_get_temp_dir().'/g-'.uniqid().'.pdf';
    file_put_contents($pdf, '%PDF-1.4 x');
    $extraction = (new GoogleDocumentAiProvider)->extract($pdf);
    @unlink($pdf);

    expect($extraction->pageCount())->toBe(2);
    expect($extraction->pages[1])->toBe('HELLO');
    expect($extraction->pages[2])->toBe('WORLD');
    expect($extraction->source)->toBe('google_document_ai');
});

// ─── Composite default provider: prefer embedded, escalate when empty ────────

it('default provider prefers embedded text and never calls Google', function (): void {
    ocrConfigureGoogle();
    Process::fake(['*' => Process::result(output: "embedded text\f")]);
    Http::fake();

    $extraction = app(DefaultEpaperOcrProvider::class)->extract('/tmp/x.pdf');

    expect($extraction->source)->toBe('embedded');
    expect($extraction->hasAnyText())->toBeTrue();
    Http::assertNothingSent();
});

it('default provider escalates to Google when embedded yields no text and Google is enabled', function (): void {
    ocrConfigureGoogle();
    Process::fake(['*' => Process::result(output: "\f\f")]); // صفحتان فارغتان (لا نصّ مضمَّن)
    ocrFakeGoogle([
        'text' => 'OCR TEXT',
        'pages' => [
            ['pageNumber' => 1, 'layout' => ['textAnchor' => ['textSegments' => [['startIndex' => '0', 'endIndex' => '8']]]]],
        ],
    ]);

    // ملفّ حقيقيّ: المضمَّن مُزيَّف (لا يقرأ) لكن مزوّد Google يقرأ البايتات فعلاً.
    $pdf = sys_get_temp_dir().'/esc-'.uniqid().'.pdf';
    file_put_contents($pdf, '%PDF-1.4 x');
    $extraction = app(DefaultEpaperOcrProvider::class)->extract($pdf);
    @unlink($pdf);

    expect($extraction->source)->toBe('google_document_ai');
    expect($extraction->hasAnyText())->toBeTrue();
});

it('default provider keeps embedded (no escalation) when Google is disabled', function (): void {
    config(['epaper.ocr.google.enabled' => false]);
    Process::fake(['*' => Process::result(output: "\f\f")]);
    Http::fake();

    $extraction = app(DefaultEpaperOcrProvider::class)->extract('/tmp/x.pdf');

    expect($extraction->source)->toBe('embedded');
    expect($extraction->hasAnyText())->toBeFalse();
    Http::assertNothingSent();
});

// ─── Backfill command (throttled re-queue) ───────────────────────────────────

it('backfill queues OCR for issues without text or failed (not done)', function (): void {
    Queue::fake();
    ocrIssue(ocrAsset()->id)->forceFill(['ocr_status' => EpaperOcrStatus::Failed->value])->save();
    ocrIssue(ocrAsset()->id); // ocr_status null
    ocrIssue(ocrAsset()->id)->forceFill(['ocr_status' => EpaperOcrStatus::Done->value])->save();

    $this->artisan('epaper:ocr-backfill')->assertSuccessful();

    Queue::assertPushed(ExtractEpaperTextJob::class, 2);
});

it('backfill --force includes already-done issues', function (): void {
    Queue::fake();
    ocrIssue(ocrAsset()->id)->forceFill(['ocr_status' => EpaperOcrStatus::Done->value])->save();

    $this->artisan('epaper:ocr-backfill', ['--force' => true])->assertSuccessful();

    Queue::assertPushed(ExtractEpaperTextJob::class, 1);
});

it('backfill respects the --limit option', function (): void {
    Queue::fake();
    ocrIssue(ocrAsset()->id);
    ocrIssue(ocrAsset()->id);
    ocrIssue(ocrAsset()->id);

    $this->artisan('epaper:ocr-backfill', ['--limit' => 2])->assertSuccessful();

    Queue::assertPushed(ExtractEpaperTextJob::class, 2);
});
