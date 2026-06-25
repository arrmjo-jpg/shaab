<?php

declare(strict_types=1);

namespace App\Health\Checks;

use App\Models\Epaper;
use App\Support\Epaper\EpaperSearchIndexer;
use Meilisearch\Exceptions\ApiException;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Throwable;

/**
 * صحّة فهرس بحث الأرشيف (Meilisearch). فاعل فقط حين يكون المحرّك هو السائق (وإلّا
 * الأرشيف يقرأ القاعدة ولا فهرس لمراقبته). إشارتان:
 *  - تعذّر الوصول للمحرّك ⇒ فشل (البحث متدهور؛ يستلزم تدخّلاً).
 *  - الفهرس فارغ/دون الحدّ الأدنى بينما توجد أعداد منشورة ⇒ فشل (فُقد/لم يُبنَ —
 *    التعافي: `php artisan epaper:search-reindex --fresh`).
 * فحص رخيص (إحصاء الفهرس + exists على المنشور) فلا يُثقِل health:check الدوريّ.
 */
class EpaperSearchHealthCheck extends Check
{
    public function run(): Result
    {
        if (! EpaperSearchIndexer::enabled()) {
            return Result::make()->ok('Archive search uses the database (Meilisearch disabled).');
        }

        try {
            $docs = (int) (EpaperSearchIndexer::index()->stats()['numberOfDocuments'] ?? 0);
        } catch (ApiException $e) {
            if ($e->errorCode !== 'index_not_found') {
                return Result::make()->failed('Meilisearch error: '.$e->getMessage());
            }
            $docs = 0; // الفهرس غير موجود بعد — نقارنه بالمنشور أدناه
        } catch (Throwable $e) {
            return Result::make()->failed('Meilisearch unreachable: '.$e->getMessage());
        }

        $minDocs = max(1, (int) config('epaper.search.health_min_documents', 1));
        $hasPublished = Epaper::query()->published()->exists();

        $result = Result::make()
            ->meta(['documents' => $docs, 'has_published' => $hasPublished])
            ->shortSummary("{$docs} pages indexed");

        if ($hasPublished && $docs < $minDocs) {
            return $result->failed(
                "Search index appears empty ({$docs} docs) while published issues exist — run `php artisan epaper:search-reindex --fresh`."
            );
        }

        return $result->ok('Archive search index healthy.');
    }
}
