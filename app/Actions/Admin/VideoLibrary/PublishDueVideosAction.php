<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Enums\VideoStatus;
use App\Models\Video;
use App\Support\Cache\VideoCacheTags;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ناشِر الفيديوهات المجدوَلة — أتمتة scheduled → published (نفس نمط الريلز/المقالات).
 *
 * فاعل النظام: published_by_id = null. idempotent: شرط WHERE + قفل صفّ + إعادة فحص
 * داخل المعاملة يمنعان السباق مع انتقال يدوي؛ قفل موزّع يمنع التداخل.
 *
 * الحارس الصارم للوسائط مُطبَّق: فيديو بلا وسائط قابلة للنشر (مرفوع غير جاهز/بلا
 * أصل) يُتخطّى ويبقى مجدوَلاً — يُعاد محاولته تلقائياً عند الجاهزية.
 */
final class PublishDueVideosAction
{
    private const LOCK_KEY = 'videos:publish-due';

    public function handle(): int
    {
        $lock = Cache::lock(self::LOCK_KEY, 110);

        if (! $lock->get()) {
            return 0; // تشغيل آخر جارٍ — تخطٍّ آمن
        }

        $published = 0;

        /** @var array<int, Video> $purgeQueue */
        $purgeQueue = [];

        try {
            Video::query()
                ->where('status', VideoStatus::Scheduled->value)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->orderBy('id')
                ->chunkById(100, function ($chunk) use (&$published, &$purgeQueue): void {
                    foreach ($chunk as $video) {
                        $publishedVideo = DB::transaction(function () use ($video): ?Video {
                            $fresh = Video::query()
                                ->whereKey($video->id)
                                ->with('mediaAsset')
                                ->lockForUpdate()
                                ->first();

                            if ($fresh === null
                                || $fresh->status !== VideoStatus::Scheduled
                                || $fresh->published_at === null
                                || $fresh->published_at->isFuture()) {
                                return null; // idempotent: تغيّرت الحالة/الوقت
                            }

                            if (! $fresh->hasPublishableMedia()) {
                                return null; // حارس صارم: يبقى مجدوَلاً
                            }

                            $fresh->status = VideoStatus::Published->value;
                            $fresh->published_by_id = null; // فاعل النظام
                            $fresh->save();

                            return $fresh;
                        });

                        if ($publishedVideo !== null) {
                            $published++;
                            $purgeQueue[] = $publishedVideo;
                        }
                    }
                });
        } finally {
            $lock->release();
        }

        if ($published > 0) {
            $tags = [];
            foreach ($purgeQueue as $video) {
                $tags = array_merge($tags, VideoCacheTags::invalidationTags(
                    $video,
                    categorySlug: $video->category?->slug,
                ));
            }
            $tags = array_values(array_unique($tags));
            Cache::tags($tags)->flush();
            FrontendRevalidate::tags(FrontendCacheTags::fromVideoTags($tags));
        }

        return $published;
    }
}
