<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Models\Video;
use App\Models\VideoPlaylist;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * تحليلات مكتبة الفيديو — مجاميع تفاعل حقيقية (لا أرقام وهمية). يجمّع
 * engagement_counters للفيديوهات القائمة (غير المحذوفة)، وأعلى قوائم التشغيل
 * بعدد الفيديوهات، والرائج بمعادلة موزونة (نفس منطق الواجهة العامة). كل القيم
 * تراكمية (مدى كامل) — تُوسَم زمنياً في الواجهة.
 */
class VideoAnalyticsAction
{
    private const TOP_LIMIT = 5;

    public function handle(): JsonResponse
    {
        // كاش قصير المدى: مجاميع تراكمية ثقيلة (مسح كامل) لا تُعاد لكل تحميل —
        // يحدّ المسح إلى مرّة لكل نافذة TTL. نفس بنية الخرج تماماً (لا أرقام وهمية).
        $data = Cache::remember('video:analytics:v1', CacheTtl::SHORT, fn (): array => $this->compute());

        return ApiResponse::success(data: $data);
    }

    /** @return array<string,mixed> */
    private function compute(): array
    {
        $morph = (new Video)->getMorphClass();

        // مجاميع التفاعل عبر الفيديوهات القائمة فقط (ربط جدول الفيديوهات يستبعد المحذوف).
        $agg = DB::table('engagement_counters as ec')
            ->join('videos as v', 'v.id', '=', 'ec.engageable_id')
            ->where('ec.engageable_type', $morph)
            ->whereNull('v.deleted_at')
            ->selectRaw('COALESCE(SUM(ec.views),0) as views, COALESCE(SUM(ec.likes),0) as likes, '
                .'COALESCE(SUM(ec.dislikes),0) as dislikes, COALESCE(SUM(ec.favorites),0) as favorites')
            ->first();

        $engagement = [
            'views' => (int) ($agg->views ?? 0),
            'likes' => (int) ($agg->likes ?? 0),
            'dislikes' => (int) ($agg->dislikes ?? 0),
            'favorites' => (int) ($agg->favorites ?? 0),
        ];

        $topPlaylists = VideoPlaylist::query()
            ->withCount('videos')
            ->orderByDesc('videos_count')
            ->orderByDesc('id')
            ->limit(self::TOP_LIMIT)
            ->get(['id', 'title', 'slug', 'locale'])
            ->map(fn (VideoPlaylist $p): array => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'locale' => $p->locale,
                'videos_count' => (int) $p->videos_count,
            ])->all();

        // الرائج: تفاعل موزون (views·1 + likes·4 + favorites·6 − dislikes·2) — عام + قابل للتشغيل.
        $score = '(COALESCE(ec.views,0) * 1 + COALESCE(ec.likes,0) * 4'
            .' + COALESCE(ec.favorites,0) * 6 - COALESCE(ec.dislikes,0) * 2)';

        $trending = Video::query()
            ->public()
            ->playable()
            ->leftJoin('engagement_counters as ec', function ($join) use ($morph): void {
                $join->on('ec.engageable_id', '=', 'videos.id')
                    ->where('ec.engageable_type', '=', $morph);
            })
            ->select('videos.id', 'videos.title', 'videos.slug', 'videos.locale', 'videos.views_count')
            ->selectRaw("{$score} as trend_score")
            ->orderByDesc('trend_score')
            ->orderByDesc('videos.published_at')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn ($v): array => [
                'id' => $v->id,
                'title' => $v->title,
                'slug' => $v->slug,
                'locale' => $v->locale,
                'views_count' => (int) $v->views_count,
                'score' => (int) $v->trend_score,
            ])->all();

        return [
            'engagement' => $engagement,
            'top_playlists' => $topPlaylists,
            'trending' => $trending,
        ];
    }
}
