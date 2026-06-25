<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Enums\ReelStatus;
use App\Models\Reel;
use App\Support\Cache\ReelCacheTags;
use App\Support\Content\ReelCdnPurge;
use App\Support\Content\ReelRevisionRecorder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ناشِر الريلز المجدوَلة — أتمتة scheduled → published (نفس نمط المقالات).
 *
 * فاعل النظام: published_by_id = null (لا فاعل بشري وقت التنفيذ).
 * idempotent: شرط WHERE يستبعد المنشور؛ قفل صفّ + إعادة فحص داخل المعاملة
 * يمنعان السباق مع انتقال يدوي؛ قفل موزّع يمنع التداخل.
 *
 * حارس الوسائط الصارم يبقى مُطبَّقاً: ريل بلا فيديو جاهز يُتخطّى ويبقى مجدوَلاً
 * (لا يُنشَر محتوى معطوب) — يُعاد محاولته تلقائياً عند جاهزية الوسائط.
 */
final class PublishDueReelsAction
{
    private const LOCK_KEY = 'reels:publish-due';

    public function handle(): int
    {
        $lock = Cache::lock(self::LOCK_KEY, 110);

        if (! $lock->get()) {
            return 0; // تشغيل آخر جارٍ — تخطٍّ آمن
        }

        $published = 0;

        /** @var array<int, Reel> $purgeQueue روابط الإبطال تُجمَّع وتُنفَّذ بعد فكّ القفل */
        $purgeQueue = [];

        try {
            Reel::query()
                ->where('status', ReelStatus::Scheduled->value)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->orderBy('id')
                ->chunkById(100, function ($chunk) use (&$published, &$purgeQueue): void {
                    foreach ($chunk as $reel) {
                        $publishedReel = DB::transaction(function () use ($reel): ?Reel {
                            // eager load mediaAsset على الصفّ المقفول → لا lazy-load
                            // داخل hasPublishableMedia() (تفادي N+1).
                            $fresh = Reel::query()
                                ->whereKey($reel->id)
                                ->with('mediaAsset')
                                ->lockForUpdate()
                                ->first();

                            if ($fresh === null
                                || $fresh->status !== ReelStatus::Scheduled
                                || $fresh->published_at === null
                                || $fresh->published_at->isFuture()) {
                                return null; // idempotent: تغيّرت الحالة/الوقت
                            }

                            // حارس صارم: لا نشر بلا فيديو جاهز — يبقى مجدوَلاً.
                            if (! $fresh->hasPublishableMedia()) {
                                return null;
                            }

                            $fresh->status = ReelStatus::Published->value;
                            $fresh->published_by_id = null; // فاعل النظام
                            $fresh->save();

                            ReelRevisionRecorder::snapshot($fresh, null);

                            return $fresh;
                        });

                        if ($publishedReel !== null) {
                            $published++;
                            $purgeQueue[] = $publishedReel;
                        }
                    }
                });
        } finally {
            $lock->release();
        }

        if ($published > 0) {
            // إبطال حبيبي: نجمّع وسوم كل ريل نُشِر (لغته + تفاصيله) ونُفرّغها دفعة
            // واحدة؛ ونُبطِل حافة الـ CDN لكل ريل (no-op إن كان CDN معطّلاً).
            $tags = [];
            foreach ($purgeQueue as $reel) {
                $tags = array_merge($tags, ReelCacheTags::invalidationTags($reel));
                ReelCdnPurge::purge($reel);
            }
            Cache::tags(array_values(array_unique($tags)))->flush();
        }

        return $published;
    }
}
