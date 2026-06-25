<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Support\Cache\ArticleCacheTags;
use App\Support\Content\ArticleCdnPurge;
use App\Support\Content\ArticleRevisionRecorder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ناشِر المقالات المجدوَلة (P2.1) — أتمتة scheduled → published.
 *
 * فاعل النظام: published_by_id = null (وeditor_id للنسخة = null).
 * مبرّر: لا فاعل بشري وقت التنفيذ؛ العمود nullable مصمَّم لتمثيل
 * النشر الآلي بأمانة، وتلفيق مستخدم وهمي مضلِّل ويستلزم صيانة.
 * مَن جدوَل المقال محفوظ أصلاً في نسخة لحظة الجدولة (provenance).
 *
 * idempotent: شرط WHERE يستبعد المنشور؛ قفل صفّ + إعادة فحص داخل
 * المعاملة يمنعان السباق مع انتقال يدوي. قفل موزّع يمنع التداخل.
 */
final class PublishDueArticlesAction
{
    private const LOCK_KEY = 'articles:publish-due';

    public function handle(): int
    {
        $lock = Cache::lock(self::LOCK_KEY, 110);

        if (! $lock->get()) {
            return 0; // تشغيل آخر جارٍ — تخطٍّ آمن (overlap-safe)
        }

        $published = 0;
        /** @var array<int,Article> $justPublished */
        $justPublished = [];

        try {
            Article::query()
                ->where('status', ArticleStatus::Scheduled->value)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->orderBy('id')
                ->chunkById(100, function ($chunk) use (&$published, &$justPublished): void {
                    foreach ($chunk as $article) {
                        DB::transaction(function () use ($article, &$published, &$justPublished): void {
                            $fresh = Article::query()->whereKey($article->id)->lockForUpdate()->first();

                            if ($fresh === null
                                || $fresh->status !== ArticleStatus::Scheduled
                                || $fresh->published_at === null
                                || $fresh->published_at->isFuture()) {
                                return; // idempotent: تغيّرت الحالة/الوقت
                            }

                            $fresh->status = ArticleStatus::Published->value;
                            $fresh->published_by_id = null; // فاعل النظام
                            $fresh->save();

                            ArticleRevisionRecorder::snapshot($fresh, null);

                            $published++;
                            $justPublished[] = $fresh;
                        });
                    }
                });
        } finally {
            $lock->release();
        }

        // نشرٌ آليّ ⇒ أظهِر المقالات فوراً: إبطال حبيبي للمنشورة + إبطال الحافة.
        if ($published > 0) {
            $tags = collect($justPublished)
                ->flatMap(fn (Article $a): array => ArticleCacheTags::writeTags($a))
                ->unique()->values()->all();
            if ($tags !== []) {
                Cache::tags($tags)->flush();
            }
            foreach ($justPublished as $article) {
                ArticleCdnPurge::purge($article);
            }
        }

        return $published;
    }
}
