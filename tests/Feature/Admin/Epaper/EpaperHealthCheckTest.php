<?php

declare(strict_types=1);

use App\Health\Checks\EpaperOcrHealthCheck;
use App\Health\Checks\EpaperSearchHealthCheck;
use App\Models\Epaper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function ehcIssue(string $ocrStatus, ?Carbon $updatedAt = null): Epaper
{
    static $n = 0;
    $n++;

    $e = Epaper::create([
        'locale' => 'ar',
        'issue_number' => 8000 + $n,
        'title' => 'صحّة '.$n,
        'slug' => 'health-'.$n,
        'publication_date' => now()->subDay()->toDateString(),
        'status' => 'draft',
        'ocr_status' => $ocrStatus,
    ]);

    if ($updatedAt !== null) {
        DB::table('epapers')->where('id', $e->id)->update(['updated_at' => $updatedAt]); // بلا لمس الطوابع
    }

    return $e;
}

// ─── Search index health ─────────────────────────────────────────────────────

it('reports search health OK when Meilisearch is not the driver (DB path)', function (): void {
    // بيئة الاختبار: SCOUT_DRIVER ليس meilisearch ⇒ لا فهرس لمراقبته.
    $result = (new EpaperSearchHealthCheck)->run();

    expect($result->status->value)->toBe('ok');
});

// ─── OCR health ──────────────────────────────────────────────────────────────

it('reports OCR health OK when there are no failures or stuck jobs', function (): void {
    ehcIssue('done');
    ehcIssue('done');

    expect((new EpaperOcrHealthCheck)->run()->status->value)->toBe('ok');
});

it('warns when failed OCR issues reach the threshold', function (): void {
    config(['epaper.ocr.health.failed_threshold' => 2]);
    ehcIssue('failed');
    ehcIssue('failed');

    expect((new EpaperOcrHealthCheck)->run()->status->value)->toBe('warning');
});

it('fails when an issue is stuck in OCR processing past the threshold', function (): void {
    config(['epaper.ocr.health.stuck_minutes' => 30]);
    ehcIssue('processing', now()->subMinutes(45)); // عالق منذ 45 دقيقة

    expect((new EpaperOcrHealthCheck)->run()->status->value)->toBe('failed');
});

it('does not count recent processing as stuck', function (): void {
    config(['epaper.ocr.health.stuck_minutes' => 30]);
    ehcIssue('processing', now()->subMinutes(5)); // قيد المعالجة حديثاً — طبيعيّ

    expect((new EpaperOcrHealthCheck)->run()->status->value)->toBe('ok');
});
