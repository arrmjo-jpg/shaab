<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * حالة مهمة مجدوَلة قابلة للتعديل. التعريفات (الأمر/التعبير) ثابتة في
 * SchedulerRegistry — هذا الجدول يخزّن فقط: التفعيل + الملاحظات + بيانات
 * التشغيل وقت الفعل.
 *
 * تدقيق: لا يستخدم AuditsChanges عمداً. مفتاحه نصّي (`key`) بينما عمود
 * activity_log.subject_id عددي، فربط النموذج كـ subject يكسر الإدراج.
 * التدقيق يتم صراحةً في UpdateScheduledTaskAction/RunScheduledTaskAction
 * عبر activity('scheduler') بلا subject — يلتقط نيّة المدير فقط بلا ضجيج
 * من الإنشاء الكسول لصفوف الحالة.
 */
class ScheduledTask extends Model
{
    /** المفتاح هو `key` (لا عمود id) — مفتاح ثابت من السجل. */
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'enabled',
        'notes',
        'last_run_at',
        'last_status',
        'last_runtime_ms',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'last_runtime_ms' => 'integer',
        ];
    }
}
