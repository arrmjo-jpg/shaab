<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\Vertix\ImportVertixNewsBatchAction;
use App\Enums\CategoryScope;
use App\Enums\CategoryStatus;
use App\Enums\VertixPhase;
use App\Models\Article;
use App\Models\Category;
use App\Models\VertixRun;
use Illuminate\Console\Command;

/**
 * استرجاع أخبار Vertix التي فشلت بـcategory_missing (قسمها الأصليّ مفقود/يتيم) وإسنادها
 * إلى قسم احتياطيّ واحد («مختارات»). Idempotent — يتخطّى ما استُورِد ولا يلمس بقيّة الأخبار.
 */
class RecoverVertixOrphanNewsCommand extends Command
{
    protected $signature = 'vertix:recover-orphan-news
        {--name=مختارات : اسم القسم الاحتياطيّ عند إنشائه}
        {--with-covers : نزّل أغلفة الأخبار أيضاً (أبطأ بكثير — تنزيل شبكيّ لكلّ خبر)}
        {--backfill-covers : اردم أغلفة الأخبار المُستورَدة بلا غلاف (تنزيل شبكيّ — بطيء)}';

    protected $description = 'استرجاع أخبار Vertix أيتام القسم (category_missing) إلى قسم «مختارات».';

    public function handle(): int
    {
        $locale = (string) config('vertix.locale', 'ar');
        $slug = ImportVertixNewsBatchAction::FALLBACK_CATEGORY_SLUG;

        // ① ضمان القسم الاحتياطيّ (Idempotent — يُحلّ منه fallback داخل الاستيراد بحسب الـslug).
        $fallback = Category::query()->where('slug', $slug)->first();
        if ($fallback === null) {
            $fallback = new Category;
            $fallback->fill([
                'locale' => $locale,
                'scope' => CategoryScope::News->value,
                'name' => trim((string) $this->option('name')) !== '' ? (string) $this->option('name') : 'مختارات',
                'status' => CategoryStatus::Active->value,
            ]);
            $fallback->slug = $slug;
            $fallback->save();
            $this->info("أُنشئ القسم الاحتياطيّ «{$fallback->name}» (id={$fallback->id}).");
        } else {
            $this->info("القسم الاحتياطيّ موجود مسبقاً: «{$fallback->name}» (id={$fallback->id}).");
        }

        // ② الأقسام الصالحة (تشمل الاحتياطيّ الآن) — أيتام القسم = ما ليس فيها.
        $validCatids = Category::query()->pluck('id')->map(static fn ($i): int => (int) $i)->all();
        $chunk = max(1, (int) config('vertix.chunk', 500));

        // وضع ردم الأغلفة: لأخبار «مختارات» المُستورَدة بلا غلاف (لا استيراد جديد — تحديث في المكان).
        if ($this->option('backfill-covers')) {
            $ids = Article::query()
                ->where('primary_category_id', $fallback->id)
                ->whereNull('og_image_id')
                ->pluck('id')->map(static fn ($i): int => (int) $i)->all();
            $this->info('ردم الأغلفة لـ'.count($ids).' خبرًا بلا غلاف (الصورة البارزة → غلاف + OG)…');
            $res = (new ImportVertixNewsBatchAction)->backfillCovers($ids, $chunk);
            $this->newLine();
            $this->info('تمّ ردم الأغلفة:');
            $this->line("  غُطّي بغلاف: {$res['covered']}");
            $this->line("  تُخطّي (بلا صورة بارزة): {$res['skipped']}");
            $this->line("  فشل تنزيل: {$res['failed']}");

            return self::SUCCESS;
        }

        $withCovers = (bool) $this->option('with-covers');
        $this->info('بدء استرجاع أيتام القسم (الأحدث ← الأقدم) — '.($withCovers ? 'مع الأغلفة' : 'بلا أغلفة (سريع؛ الصورة البارزة تبقى داخل المتن)').'…');
        $action = new ImportVertixNewsBatchAction;
        if (! $withCovers) {
            $action->withoutCovers();
        }
        $result = $action->recoverOrphans($validCatids, $chunk);

        // ③ مزامنة عدّادات الـrun: المُستورَد يزيد بالمُسترجَع، والفاشل يصبح الفشل الحقيقيّ المتبقّي
        //    (لا category_missing بعد الآن)، وتُمسح أخطاء category_missing القديمة.
        $run = VertixRun::forPhase(VertixPhase::News);
        $run->forceFill([
            'imported' => $run->imported + $result['imported'],
            'failed' => $result['failed'],
            'errors' => $result['errors'],
        ])->save();

        $media = $action->mediaCounts();
        $this->newLine();
        $this->info('تمّ الاسترجاع:');
        $this->line("  مُستورَد: {$result['imported']}");
        $this->line("  متخطّى (موجود سلفاً): {$result['skipped']}");
        $this->line("  فاشل (حقيقيّ متبقٍّ): {$result['failed']}");
        $this->line("  أغلفة: نجح {$media['imported']} / فشل {$media['failed']}");

        return self::SUCCESS;
    }
}
