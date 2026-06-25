<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Notifications\Actions\SyncEventCatalogAction;
use Illuminate\Database\Seeder;

/**
 * يُزامن EventCatalog (المصدر الكوديّ) إلى notification_events + المصفوفة عبر SyncEventCatalogAction
 * (idempotent، يحفظ enabled للأدمن، يؤرشف المحذوف لا يحذفه).
 */
class NotificationEventCatalogSeeder extends Seeder
{
    public function run(): void
    {
        (new SyncEventCatalogAction)->handle();
    }
}
