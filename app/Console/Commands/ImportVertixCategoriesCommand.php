<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\Vertix\ImportVertixCategoriesAction;
use Illuminate\Console\Command;

/** المرحلة الأولى عبر سطر الأوامر — استيراد أقسام Vertix (Idempotent). */
class ImportVertixCategoriesCommand extends Command
{
    protected $signature = 'vertix:import-categories';

    protected $description = 'استيراد أقسام Vertix إلى تصنيفات AlphaCMS (المرحلة الأولى).';

    public function handle(): int
    {
        $run = (new ImportVertixCategoriesAction)->handle();
        $this->info("تمّ. أقسام مُستورَدة: {$run->imported}، فاشلة: {$run->failed}");

        return self::SUCCESS;
    }
}
