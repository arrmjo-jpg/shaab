<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Models\Reel;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * عدّادات لوحة الريلز (بطاقات الحالة) — استعلام تجميعي واحد للحالات + عدّ المحذوف.
 * builder خام (لا hydration) كي تبقى مفاتيح status نصوصاً لا enum.
 */
class ReelStatsAction
{
    public function handle(): JsonResponse
    {
        $byStatus = DB::table('reels')
            ->whereNull('deleted_at')
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as aggregate')
            ->pluck('aggregate', 'status');

        $counts = [
            'draft' => (int) ($byStatus['draft'] ?? 0),
            'scheduled' => (int) ($byStatus['scheduled'] ?? 0),
            'published' => (int) ($byStatus['published'] ?? 0),
            'archived' => (int) ($byStatus['archived'] ?? 0),
        ];

        return ApiResponse::success(data: [
            ...$counts,
            'total' => array_sum($counts),
            'trashed' => Reel::onlyTrashed()->count(),
        ]);
    }
}
