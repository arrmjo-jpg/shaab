<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EpaperDailyStat;
use App\Models\EpaperIssueStat;
use App\Models\EpaperPageView;
use App\Models\EpaperSearchTerm;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * يطبّق ملخّص جلسة قراءة على عدّادات التحليلات المجمَّعة (Phase 5). queue-safe
 * (زيادات ذرّية)، واعٍ للخصوصية (لا هوية مستخدم/IP). يُستدعى من بيكون نهاية الجلسة.
 *
 * Enterprise: مَعزول على طابور «analytics» (عالي الحجم/منخفض الأولويّة) فلا يُزاحم
 * المهامّ التفاعليّة على الافتراضيّ؛ بمهلة محدودة؛ والفشل الدائم يُسجَّل ويُبتلَع (فقد
 * تحليلٍ مجمَّع غير حرج — لا نُسقِط عاملاً بسببه).
 */
class RecordEpaperReadingSessionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @param  array{duration?:int,pages?:array<int,int>,searches?:array<int,string>,bookmarks_used?:int,resumed?:bool}  $session
     */
    public function __construct(
        public readonly int $epaperId,
        public readonly array $session,
    ) {
        $this->onQueue((string) config('epaper.analytics.queue', 'analytics'));
    }

    public function handle(): void
    {
        $pages = array_values(array_unique(array_filter(
            $this->session['pages'] ?? [],
            static fn ($p): bool => is_int($p) && $p >= 1,
        )));

        $terms = $this->normalizeTerms($this->session['searches'] ?? []);
        $duration = max(0, (int) ($this->session['duration'] ?? 0));
        $bookmarksUsed = max(0, (int) ($this->session['bookmarks_used'] ?? 0));
        $resumed = (bool) ($this->session['resumed'] ?? false);

        DB::transaction(function () use ($pages, $terms, $duration, $bookmarksUsed, $resumed): void {
            $stat = EpaperIssueStat::query()->firstOrCreate(['epaper_id' => $this->epaperId]);

            // زيادات ذرّية (UPDATE col = col + n) — آمنة تحت التزامن.
            $stat->increment('opens');
            $stat->increment('sessions');
            $stat->increment('total_duration_seconds', $duration);
            $stat->increment('pages_viewed', count($pages));
            $stat->increment('searches', count($terms));
            $stat->increment('bookmarks_used', $bookmarksUsed);
            $stat->increment('resumes_used', $resumed ? 1 : 0);
            $stat->forceFill(['last_activity_at' => now()])->save();

            // تجميعة يوميّة (Final completion) — تُمكّن مرشّحات المدى الزمنيّ في اللوحة.
            // تحديث ذرّيّ واحد (raw) بدل سبع زيادات — أكفأ للحجم العالي. القيم أعداد
            // صحيحة مُحقّقة (لا حقن). downloads تُغذَّى من وظيفة التنزيل لا من هنا.
            $daily = EpaperDailyStat::query()->firstOrCreate([
                'epaper_id' => $this->epaperId,
                'stat_date' => now()->toDateString(),
            ]);
            EpaperDailyStat::query()->whereKey($daily->id)->update([
                'opens' => DB::raw('opens + 1'),
                'sessions' => DB::raw('sessions + 1'),
                'total_duration_seconds' => DB::raw('total_duration_seconds + '.$duration),
                'pages_viewed' => DB::raw('pages_viewed + '.count($pages)),
                'searches' => DB::raw('searches + '.count($terms)),
                'bookmarks_used' => DB::raw('bookmarks_used + '.$bookmarksUsed),
                'resumes_used' => DB::raw('resumes_used + '.($resumed ? 1 : 0)),
            ]);

            foreach ($pages as $page) {
                EpaperPageView::query()
                    ->firstOrCreate(['epaper_id' => $this->epaperId, 'page_number' => $page])
                    ->increment('views');
            }

            foreach ($terms as $term) {
                EpaperSearchTerm::query()
                    ->firstOrCreate(['epaper_id' => $this->epaperId, 'term' => $term])
                    ->increment('count');
            }
        });
    }

    /**
     * تطبيع عبارات البحث: قصّ + حدّ الطول (100) + إزالة التكرار (بخفض الحالة) + سقف 30.
     *
     * @param  array<int,mixed>  $raw
     * @return array<int,string>
     */
    private function normalizeTerms(array $raw): array
    {
        $out = [];
        foreach ($raw as $term) {
            $t = mb_substr(trim((string) $term), 0, 100);
            if ($t !== '') {
                $out[mb_strtolower($t)] = $t;
            }
        }

        return array_slice(array_values($out), 0, 30);
    }

    public function failed(?Throwable $e): void
    {
        // تحليلات مجمَّعة غير حرجة — سجّل الفشل الدائم دون إسقاط العامل.
        Log::warning('epaper.analytics.session_failed', [
            'epaper_id' => $this->epaperId,
            'error' => $e?->getMessage(),
        ]);
    }
}
