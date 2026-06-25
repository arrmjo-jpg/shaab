<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * قاعدة التدقيق الموحّدة (Spatie Activitylog) — تُستخدَم في كل نموذج Eloquent.
 *
 * النموذج يجب أن يعرّف:
 *   protected string $auditLogName = 'user';
 *   protected array  $auditAttributes = ['name', 'email', ...]; // بلا أي أسرار
 *
 * أسرار مستبعَدة عالمياً دائماً كحزام أمان إضافي.
 * لا تتجاوز أحداث Eloquent (DB::table / saveQuietly / withoutEvents) لما هو مُدقَّق.
 */
trait AuditsChanges
{
    use LogsActivity;

    /** مفاتيح حسّاسة لا تُسجَّل أبداً مهما كان الإعداد. */
    private const GLOBAL_SECRETS = [
        'password',
        'remember_token',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName($this->auditLogName ?? class_basename($this))
            ->logOnly($this->auditAttributes ?? [])
            ->logExcept(self::GLOBAL_SECRETS)
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event): string => __('audit.event.'.$event));
    }

    /**
     * Spatie v5 يكتب فرق السمات في عمود attribute_changes، بينما عقد المنصّة (موارد
     * العرض والتعقيم والسجلّات اليدوية auth/settings) يقرأ الفرق من properties.{attributes,old}.
     * نُسقط الفرق إلى properties — مصدر وحيد للحقيقة — كي يظهر ويُعقَّم. خطّاف على الذات يوفّره
     * Spatie (LogActivityAction::beforeActivityLogged) فيغطّي كل نموذج مُدقَّق مركزياً بلا تكرار.
     */
    public function beforeActivityLogged(Model $activity, string $eventName): void
    {
        $changes = $activity->attribute_changes;

        if ($changes === null || count($changes) === 0) {
            return;
        }

        $activity->properties = collect($activity->properties ?? [])->merge($changes);
        $activity->attribute_changes = null;
    }
}
