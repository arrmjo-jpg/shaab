<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\Whatsapp\TickWhatsappCampaignsAction;
use Illuminate\Console\Command;

/**
 * يطلق حملات واتساب المجدوَلة المستحقّة — يُدار عبر SchedulerRegistry (everyMinute).
 * idempotent وآمن عند عدم وجود مستحقّ.
 */
class TickWhatsappCampaignsCommand extends Command
{
    protected $signature = 'whatsapp:campaigns-tick';

    protected $description = 'Dispatch due scheduled WhatsApp campaigns.';

    public function handle(TickWhatsappCampaignsAction $action): int
    {
        $count = $action->handle();

        $this->info("Dispatched {$count} scheduled campaign(s).");

        return self::SUCCESS;
    }
}
