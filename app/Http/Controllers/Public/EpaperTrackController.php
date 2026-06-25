<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\EpaperAccessLevel;
use App\Http\Controllers\Controller;
use App\Jobs\RecordEpaperReadingSessionJob;
use App\Models\Epaper;
use App\Support\Epaper\EpaperAccessPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * استقبال ملخّص جلسة قراءة (Phase 5) — بيكون واحد عند نهاية الجلسة (لا تتبّع
 * ضوضائيّ لكل إجراء). مجهول (واعٍ للخصوصية): لا تُخزَّن هوية مستخدم/IP. يدفع
 * التجميع إلى وظيفة مُجدوَلة (queue-safe). يحترم canView كي لا يُسجَّل عددٌ محجوب.
 */
class EpaperTrackController extends Controller
{
    public function store(Request $request, string $locale, string $issue): JsonResponse
    {
        $epaper = Epaper::query()->published()->forLocale($locale)->whereKey((int) $issue)->first();
        abort_if($epaper === null, 404);

        if (! app(EpaperAccessPolicy::class)->canView($request->user(), $epaper)) {
            abort_if($epaper->access_level === EpaperAccessLevel::Private, 404);
            abort(403);
        }

        $data = $request->validate([
            'duration' => ['required', 'integer', 'min:0', 'max:86400'],
            'pages' => ['sometimes', 'array', 'max:1000'],
            'pages.*' => ['integer', 'min:1'],
            'searches' => ['sometimes', 'array', 'max:30'],
            'searches.*' => ['string', 'max:100'],
            'bookmarks_used' => ['sometimes', 'integer', 'min:0', 'max:5000'],
            'resumed' => ['sometimes', 'boolean'],
        ]);

        RecordEpaperReadingSessionJob::dispatch($epaper->id, [
            'duration' => (int) ($data['duration'] ?? 0),
            'pages' => array_map('intval', $data['pages'] ?? []),
            'searches' => array_values($data['searches'] ?? []),
            'bookmarks_used' => (int) ($data['bookmarks_used'] ?? 0),
            'resumed' => (bool) ($data['resumed'] ?? false),
        ]);

        return response()->json(['accepted' => true])->header('Cache-Control', 'no-store, max-age=0');
    }
}
