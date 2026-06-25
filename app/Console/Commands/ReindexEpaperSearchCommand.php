<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Epaper;
use App\Models\EpaperPage;
use App\Support\Epaper\EpaperSearchIndexer;
use Illuminate\Console\Command;
use Meilisearch\Exceptions\ApiException;

/**
 * يعيد بناء فهرس بحث أرشيف الجريدة (Meilisearch) من قاعدة البيانات — مصدر الحقيقة.
 * الفهرس مشتقّ بالكامل (لا نسخ احتياطيّ له)، فهذا الأمر هو مسار التعافي بعد فقد/تلف
 * الفهرس أو تغيير المخطّط. --fresh يمسح الفهرس ويطبّق إعداداته من config (مكتفٍ ذاتياً)؛
 * --queue يوزّع المزامنة على طابور «search» (أسرع للمقياس الكبير)؛ وإلّا تنفيذ متزامن.
 */
class ReindexEpaperSearchCommand extends Command
{
    protected $signature = 'epaper:search-reindex
        {--fresh : امسح الفهرس وأعِد ضبط إعداداته قبل إعادة البناء}
        {--queue : وزّع المزامنة على طابور البحث بدل التنفيذ المتزامن}
        {--limit=0 : حدّ أقصى لعدد الأعداد المُعالَجة (0 = الكل)}';

    protected $description = 'يعيد بناء فهرس بحث أرشيف الجريدة (Meilisearch) من قاعدة البيانات.';

    public function handle(): int
    {
        if (! EpaperSearchIndexer::enabled()) {
            $this->warn('SCOUT_DRIVER ليس meilisearch — لا فهرس لإعادة بنائه (الأرشيف يقرأ القاعدة مباشرةً).');

            return self::SUCCESS;
        }

        if ($this->option('fresh')) {
            $this->applyFreshIndex();
        }

        $limit = max(0, (int) $this->option('limit'));
        $useQueue = (bool) $this->option('queue');
        $count = 0;

        Epaper::query()->published()->orderBy('id')
            ->chunkById(200, function ($issues) use (&$count, $limit, $useQueue): bool {
                foreach ($issues as $issue) {
                    if ($limit > 0 && $count >= $limit) {
                        return false;
                    }
                    $useQueue
                        ? EpaperSearchIndexer::queueSync($issue->id)
                        : EpaperSearchIndexer::reindexIssue($issue);
                    $count++;
                }

                return true;
            });

        $verb = $useQueue ? 'queued' : 'reindexed';
        $this->info("Archive search {$verb} for {$count} published issue(s).");

        return self::SUCCESS;
    }

    /** يضمن وجود الفهرس، يمسح وثائقه، ويطبّق إعداداته من config — تعافٍ مكتفٍ ذاتياً. */
    private function applyFreshIndex(): void
    {
        $client = EpaperSearchIndexer::client();

        try {
            $client->createIndex(EpaperPage::SEARCH_INDEX, ['primaryKey' => 'id']);
        } catch (ApiException $e) {
            if ($e->errorCode !== 'index_already_exists') {
                throw $e;
            }
        }

        $index = $client->index(EpaperPage::SEARCH_INDEX);
        $index->deleteAllDocuments();

        $settings = (array) config('scout.meilisearch.index-settings.'.EpaperPage::SEARCH_INDEX, []);
        if ($settings !== []) {
            $index->updateSettings($settings);
        }

        $this->line('Fresh index ensured + settings applied.');
    }
}
