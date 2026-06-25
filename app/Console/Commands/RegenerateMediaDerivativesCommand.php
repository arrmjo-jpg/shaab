<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\Media\RegenerateMediaDerivativesAction;
use Illuminate\Console\Command;

/**
 * إعادة توليد مشتقّات الصور لكل المكتبة (CLI) — مكافئ لزرّ لوحة الإدارة.
 * يُجدوِل وظائف الخلفية؛ لا يحوّل بشكل متزامن.
 */
class RegenerateMediaDerivativesCommand extends Command
{
    protected $signature = 'media:regenerate-derivatives';

    protected $description = 'Queue derivative regeneration (thumb/medium/watermarked) for all image library assets.';

    public function handle(RegenerateMediaDerivativesAction $action): int
    {
        $count = $action->handle();

        $this->info("Queued {$count} image asset(s) for derivative regeneration.");

        return self::SUCCESS;
    }
}
