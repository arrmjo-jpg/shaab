<?php

declare(strict_types=1);

namespace App\Actions\Admin\Vertix;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Models\Article;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\VertixRun;
use App\Support\Content\SlugGenerator;
use App\Support\Vertix\VertixAuthor;
use App\Support\Vertix\VertixContentTransformer;
use App\Support\Vertix\VertixImageImporter;
use App\Support\Vertix\VertixImageUrl;
use App\Support\Vertix\VertixSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * المرحلة الثانية: استيراد أخبار Vertix بحفظ المعرّف الأصليّ مباشرةً —
 * articles.id = art_news.newsid (بلا mapping/source_id/تحويل). الترتيب الأحدث ← الأقدم.
 *
 * المطابقة/الدّيدوب بالـ id نفسه على جدول articles: موجود ⇒ تخطٍّ (لا تكرار)؛ غير
 * موجود ⇒ إنشاء بمعرّفه. القسم مباشرة: primary_category_id = catid (= categories.id).
 * الصورة رابط مُولَّد من folder+ph_name (لا يُخزَّن). Idempotent، إعادة تشغيل آمنة.
 */
class ImportVertixNewsBatchAction
{
    private int $authorId;

    private User $actor;

    private string $locale;

    private int $mediaImported = 0;

    private int $mediaFailed = 0;

    public function __construct()
    {
        $this->actor = VertixAuthor::resolve();
        $this->authorId = $this->actor->id;
        $this->locale = (string) config('vertix.locale', 'ar');
    }

    /** @return array{imported:int,failed:int} عدّادات تنزيل صور الأغلفة (للتقارير/الإثبات الحيّ). */
    public function mediaCounts(): array
    {
        return ['imported' => $this->mediaImported, 'failed' => $this->mediaFailed];
    }

    /** تهيئة عند أوّل تشغيل: سقف الردم = أعلى newsid (يبدأ من الأحدث). Idempotent. */
    public static function initialize(VertixRun $run): void
    {
        if (! $run->backfill_done && $run->cursor === 0 && $run->high_water === 0 && $run->imported === 0) {
            $max = VertixSource::make()->maxNewsId();
            $run->forceFill(['high_water' => $max, 'cursor' => $max + 1])->save();
        }
    }

    /**
     * دفعة واحدة (الأحدث ← الأقدم): ① تلتقط الجديد فوق high_water، ② ثمّ تردم تنازلياً
     * تحت cursor. تتخطّى الموجود (articles.id) — Idempotent بلا تكرار.
     *
     * @return array{processed:int,imported:int,failed:int,skipped:int,done:bool}
     */
    public function handleRun(VertixRun $run, int $limit): array
    {
        $source = VertixSource::make();

        // ① الجديد فوق العلامة العليا (الأحدث أولاً).
        $newRows = $source->newsAbove($run->high_water, $limit);
        if ($newRows !== []) {
            $r = $this->importRows($newRows);
            $run->forceFill([
                'high_water' => max(array_map(static fn ($x): int => (int) $x->newsid, $newRows)),
                'imported' => $run->imported + $r['imported'],
                'failed' => $run->failed + $r['failed'],
            ])->save();
            $this->appendErrors($run, $r['errors']);

            return $this->summary($r, false);
        }

        // ② الردم التنازليّ (تحت المؤشّر نحو الأقدم).
        if (! $run->backfill_done) {
            $below = $run->cursor > 0 ? $run->cursor : ($source->maxNewsId() + 1);
            $rows = $source->newsBelow($below, $limit);
            if ($rows === []) {
                $run->forceFill(['backfill_done' => true])->save();

                return ['processed' => 0, 'imported' => 0, 'failed' => 0, 'skipped' => 0, 'done' => true];
            }
            $r = $this->importRows($rows);
            $run->forceFill([
                'cursor' => min(array_map(static fn ($x): int => (int) $x->newsid, $rows)),
                'imported' => $run->imported + $r['imported'],
                'failed' => $run->failed + $r['failed'],
            ])->save();
            $this->appendErrors($run, $r['errors']);

            return $this->summary($r, false);
        }

        return ['processed' => 0, 'imported' => 0, 'failed' => 0, 'skipped' => 0, 'done' => true];
    }

    /**
     * يستورد الصفوف غير الموجودة (المطابقة بوجود articles.id = newsid) — بلا تكرار.
     *
     * @param  array<int,object>  $rows
     * @return array{processed:int,imported:int,failed:int,skipped:int,errors:array<int,array<string,mixed>>}
     */
    private function importRows(array $rows): array
    {
        $ids = array_map(static fn ($x): int => (int) $x->newsid, $rows);
        $existing = Article::query()->whereIn('id', $ids)->pluck('id')->flip();

        $imported = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $row) {
            $nid = (int) $row->newsid;
            if ($existing->has($nid)) {
                $skipped++;

                continue; // موجود بنفس المعرّف ⇒ لا إعادة (Idempotent)
            }
            try {
                $this->importOne($row);
                $imported++;
            } catch (Throwable $e) {
                $failed++;
                $errors[] = ['type' => 'news', 'id' => $nid, 'error' => mb_substr($e->getMessage(), 0, 300), 'at' => now()->toISOString()];
            }
        }

        return ['processed' => count($rows), 'imported' => $imported, 'failed' => $failed, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function importOne(object $row): void
    {
        $categoryId = (int) $row->catid; // = categories.id مباشرةً
        if (Category::query()->whereKey($categoryId)->doesntExist()) {
            throw new RuntimeException('category_missing:'.$categoryId);
        }

        // ① رابط الصورة البارزة (يُستخدَم مرّتين: تنزيلًا للغلاف، ومطابقةً لإزالة تكرارها من صدر المتن).
        $featuredUrl = VertixImageUrl::build($row->folder ?? null, $row->ph_name ?? null);

        // ② الصورة البارزة → MediaAsset غلافاً (خارج المعاملة: تنزيل شبكيّ). ديدوب SHA-256 طبيعيّ.
        $cover = $this->resolveCover($featuredUrl);

        // ③ المتن يُحفَظ كاملاً (صور/روابط/عناوين/قوائم/جداول/اقتباسات/تضمينات) عبر HtmlToTipTap؛
        //    تُزال **فقط** الصورة البارزة إن تكرّرت في صدر المتن (صارت غلافاً). صفر strip_tags، صفر فقدان.
        $tx = VertixContentTransformer::transform($row->body ?? null, $featuredUrl);

        // ③ إبقاء ذرّيّ: المقال (بمعرّفه الأصليّ) + ربط الغلاف.
        DB::transaction(function () use ($row, $categoryId, $tx, $cover): void {
            $article = new Article;
            $article->incrementing = false;
            $article->id = (int) $row->newsid; // حفظ المعرّف الأصليّ
            $article->fill([
                'author_id' => $this->authorId,
                'published_by_id' => $this->authorId,
                'primary_category_id' => $categoryId,
                'type' => ArticleType::News->value,
                'status' => ArticleStatus::Published->value,
                'locale' => $this->locale,
                'title' => $this->title($row),
                'excerpt' => $this->excerpt($row),
                'content' => $tx['html'],
                'content_json' => $tx['doc'],
                'og_image_id' => $cover?->id, // الغلاف (OG + cover)
                'seo_title' => $this->title($row),
                'seo_keywords' => trim((string) ($row->keywords ?? '')) !== '' ? mb_substr((string) $row->keywords, 0, 255) : null,
                'comments_enabled' => false,
                'views_count' => (int) ($row->views ?? 0),
                'published_at' => $this->publishedAt($row),
            ]);
            $article->slug = $this->slug($row);
            $article->save();

            // ربط الغلاف — Idempotent (syncWithoutDetaching: لا صفوف مكرّرة في article_media).
            if ($cover !== null) {
                $article->mediaAssets()->syncWithoutDetaching([
                    $cover->id => ['collection' => 'cover', 'position' => 0],
                ]);
            }
        });
    }

    /**
     * يُدخل رابط الصورة البارزة إلى MediaAsset (محدِّثاً عدّادات النجاح/الفشل). رابط null/فارغ
     * (لا folder/ph_name) ⇒ null دون احتساب فشل (الخبر بلا صورة بارزة، ليس فشل تنزيل).
     */
    private function resolveCover(?string $url): ?MediaAsset
    {
        if ($url === null) {
            return null;
        }

        $asset = VertixImageImporter::fetch($url, $this->actor);
        if ($asset !== null) {
            $this->mediaImported++;
        } else {
            $this->mediaFailed++;
        }

        return $asset;
    }

    private function title(object $row): string
    {
        $t = trim((string) ($row->title ?? ''));

        return $t !== '' ? mb_substr($t, 0, 200) : ('خبر '.(int) $row->newsid);
    }

    private function excerpt(object $row): ?string
    {
        $b = trim(strip_tags((string) ($row->brief ?? '')));

        return $b !== '' ? mb_substr($b, 0, 500) : null;
    }

    /** slug فريد بلا استعلام per-row (newsid فريد؛ المعرّف = newsid هو مفتاح المسار). */
    private function slug(object $row): string
    {
        $src = trim((string) ($row->link ?? '')) !== '' ? (string) $row->link : (string) $row->title;
        $base = mb_substr(SlugGenerator::makeWithFallback($src), 0, 170);
        if ($base === '') {
            $base = 'news';
        }

        return $base.'-'.(int) $row->newsid;
    }

    private function publishedAt(object $row): Carbon
    {
        $ts = (int) ($row->updatedate_int ?? 0);
        if ($ts > 0) {
            return Carbon::createFromTimestamp($ts);
        }
        $date = trim((string) ($row->createdate ?? ''));
        try {
            return $date !== '' ? Carbon::parse($date) : now();
        } catch (Throwable) {
            return now();
        }
    }

    /** @param  array{processed:int,imported:int,failed:int,skipped:int,errors:array<int,mixed>}  $r */
    private function summary(array $r, bool $done): array
    {
        return ['processed' => $r['processed'], 'imported' => $r['imported'], 'failed' => $r['failed'], 'skipped' => $r['skipped'], 'done' => $done, 'media_imported' => $this->mediaImported, 'media_failed' => $this->mediaFailed];
    }

    /** @param  array<int,array<string,mixed>>  $new */
    private function appendErrors(VertixRun $run, array $new): void
    {
        if ($new === []) {
            return;
        }
        $merged = array_merge($run->errors ?? [], $new);
        $run->forceFill(['errors' => array_slice($merged, -50)])->save();
    }
}
