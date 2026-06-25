<?php

declare(strict_types=1);

namespace App\Jobs\WpMigration;

use App\Actions\Admin\WpMigration\ImportWpPostAction;
use App\Enums\MigrationItemStatus;
use App\Enums\MigrationRunStatus;
use App\Models\MigrationItem;
use App\Models\MigrationRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * يستورد منشوراً واحداً (عنصر دفتر) داخل عامل الطابور. التفرّد بمُعرّف العنصر يضمن
 * ألّا يُعالَج wp_post_id واحد بمهمّتين متزامنتين (قاعدة #8): القفل قائم طوال المعالجة.
 *
 * احترام دورة الحياة (#6/#7): لا تنفيذ إلا حين running — إن أُوقِفت التشغيلة مؤقّتاً/
 * أُوقِفت بقي العنصر بحالته كما هو فيلتقطه التوزيع لاحقاً من الدفتر (لا قتل وسط عمل).
 *
 * مطالبة ذرّية بحارس عدد الصفوف: تُحوِّل عنصراً قابلاً للمعالجة فقط (pending/queued/
 * failed تحت السقف) إلى processing — صفر صفوف ⇒ التُقِط/انتهى/استُنفِد فلا تكرار.
 * إعادة المحاولة يقودها التوزيع (failed<cap) لا الطابور: حتمية بلا تضخّم (#5).
 */
class ImportWpPostJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    // القفل قائم حتى انتهاء المعالجة؛ uniqueFor سقفٌ أمان لو مات العامل (يُحرَّر بعده).
    public int $uniqueFor = 600;

    public function __construct(
        private readonly int $runId,
        private readonly int $itemId,
    ) {
        $this->onQueue((string) config('wp-migration.queue', 'migration'));
    }

    public function uniqueId(): string
    {
        return 'wpmig-item-'.$this->itemId;
    }

    public function handle(): void
    {
        $run = MigrationRun::query()->find($this->runId);
        if ($run === null || $run->status !== MigrationRunStatus::Running) {
            return; // إيقاف مؤقّت/إيقاف/انتهاء ⇒ لا عمل جديد (#6/#7)
        }

        $cap = max(1, (int) config('wp-migration.item_tries', 3));

        $claimed = MigrationItem::query()
            ->where('id', $this->itemId)
            ->where('run_id', $this->runId)
            ->whereIn('status', [
                MigrationItemStatus::Pending->value,
                MigrationItemStatus::Queued->value,
                MigrationItemStatus::Failed->value,
            ])
            ->where('attempts', '<', $cap)
            ->update([
                'status' => MigrationItemStatus::Processing->value,
                'last_step' => 'claim',
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return; // التُقِط بالفعل/منتهٍ/استُنفِدت محاولاته — لا تكرار (#8)
        }

        $item = MigrationItem::query()->where('id', $this->itemId)->first();
        if ($item === null) {
            return;
        }

        // الفشل المُصنَّف وحدود المعاملة لكل منشور داخل الـ Action (عزل العنصر السامّ #11).
        ImportWpPostAction::for($run)->handle($item);
    }
}
