<?php

declare(strict_types=1);

namespace App\Support\Epaper;

use App\Enums\EpaperStatus;
use App\Jobs\SyncEpaperSearchIndexJob;
use App\Models\Epaper;
use App\Models\EpaperPage;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;

/**
 * مصدر الحقيقة الوحيد لمزامنة فهرس بحث الأرشيف (Meilisearch، على مستوى الصفحة).
 *
 * لماذا فهرسة مُدارة صراحةً (لا سمة Scout self-syncing)؟ دورة حياة صفحات العدد كتليّة
 * (حذف+إعادة بناء عند كل OCR، وهو مسارٌ يتجاوز أحداث النموذج) وتتعاقب من حالة العدد
 * (نشر/إلغاء/تحديث وصول) — وكلاهما لا يناسب مزامنة Scout لكل نموذج. عمليّتان بالجملة
 * لكل عدد (حذف-بالمرشّح + إضافة مُجمَّعة) أكفأ بكثير من مئات وظائف المزامنة الفرديّة.
 *
 * فاشل-مرتجِع: العمليّات تتصاعد فيها الاستثناءات لتُعيد وظيفةُ الطابور المحاولة؛ والمسار
 * التحريريّ لا يُحجَب أبداً (المزامنة تُجدوَل عبر queueSync فقط، لا متزامنة).
 */
final class EpaperSearchIndexer
{
    /** حجم دفعة الإضافة إلى المحرّك (يوازن الذاكرة مقابل عدد الطلبات). */
    private const CHUNK = 500;

    /** الفهرسة العابرة مفعّلة فقط حين يكون محرّك Scout هو Meilisearch (مسار المقياس). */
    public static function enabled(): bool
    {
        return config('scout.driver') === 'meilisearch';
    }

    /** يُجدوِل مزامنة فهرس عددٍ على طابور البحث (لا يحجب المسار التحريريّ). لا فعل ما لم يُفعَّل. */
    public static function queueSync(int $epaperId): void
    {
        if (self::enabled()) {
            SyncEpaperSearchIndexJob::dispatch($epaperId);
        }
    }

    /**
     * إعادة فهرسة صفحات عددٍ كاملةً (متزامن — يُستدعى من الوظيفة/أمر الفهرسة): حذف
     * وثائقه القائمة بالمرشّح (يزيل أيتام إعادة البناء ذات المعرّفات القديمة) ثم إضافة
     * صفحاته القابلة للفهرسة الحاليّة مُجمَّعةً. غير المؤهّل (مسودّة/محذوف) ⇒ إزالة فقط.
     */
    public static function reindexIssue(Epaper $issue): void
    {
        if (! self::enabled()) {
            return;
        }

        self::purge($issue->id);

        if (! self::issueSearchable($issue)) {
            return;
        }

        $index = self::index();
        EpaperPage::query()
            ->where('epaper_id', $issue->id)
            ->where('has_text', true)
            ->chunkById(self::CHUNK, function ($pages) use ($index, $issue): void {
                $docs = $pages->map(fn (EpaperPage $p): array => $p->toSearchDocument($issue))->all();
                if ($docs !== []) {
                    $index->addDocuments($docs, 'id');
                }
            });
    }

    /** يزيل كل وثائق عددٍ من الفهرس (حذف نهائيّ / تطهير). */
    public static function removeIssue(int $epaperId): void
    {
        if (! self::enabled()) {
            return;
        }
        self::purge($epaperId);
    }

    /** أهليّة العدد للظهور في الأرشيف: منشور غير مجدوَل وغير محذوف. */
    public static function issueSearchable(Epaper $issue): bool
    {
        return $issue->deleted_at === null
            && $issue->status === EpaperStatus::Published
            && $issue->published_at !== null
            && ! $issue->published_at->isFuture();
    }

    /** عميل Meilisearch مُهيّأ من إعداد Scout (يُعاد استخدامه: الفهرسة + المراقبة + التعافي). */
    public static function client(): Client
    {
        return new Client(
            (string) config('scout.meilisearch.host'),
            config('scout.meilisearch.key'),
        );
    }

    /** فهرس Meilisearch لصفحات الجريدة. */
    public static function index(): Indexes
    {
        return self::client()->index(EpaperPage::SEARCH_INDEX);
    }

    /** حذف وثائق عددٍ بالمرشّح؛ «الفهرس غير موجود» ليس خطأً (لا شيء لإزالته بعد). */
    private static function purge(int $epaperId): void
    {
        try {
            self::index()->deleteDocuments(['filter' => 'epaper_id = '.$epaperId]);
        } catch (ApiException $e) {
            if ($e->errorCode !== 'index_not_found') {
                throw $e;
            }
        }
    }
}
