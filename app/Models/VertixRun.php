<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VertixPhase;
use App\Enums\VertixRunStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * حالة مرحلة ترحيل Vertix (categories|news) — صفّ واحد لكلّ مرحلة. نموذج تشغيليّ
 * (غير مُدقَّق، نظير نماذج تتبّع الترحيل) يحمل العدّادات والعلامة المائيّة للتزايد.
 *
 * @property VertixPhase $phase
 * @property VertixRunStatus $status
 */
class VertixRun extends Model
{
    protected $table = 'vertix_runs';

    protected $fillable = [
        'phase', 'status', 'total', 'imported', 'failed',
        'high_water', 'cursor', 'backfill_done', 'errors',
        'last_error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'phase' => VertixPhase::class,
            'status' => VertixRunStatus::class,
            'total' => 'integer',
            'imported' => 'integer',
            'failed' => 'integer',
            'high_water' => 'integer',
            'cursor' => 'integer',
            'backfill_done' => 'boolean',
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** يجلب (أو يهيّئ) صفّ المرحلة — مفتاحه phase الفريد. */
    public static function forPhase(VertixPhase $phase): self
    {
        // refresh() يضمن تحميل القيم الافتراضيّة للأعمدة (0/false) بعد الإنشاء —
        // وإلا تبقى null في الذاكرة فتفشل فحوص التهيئة الصارمة (=== 0).
        return self::query()->firstOrCreate(
            ['phase' => $phase->value],
            ['status' => VertixRunStatus::Idle->value],
        )->refresh();
    }
}
