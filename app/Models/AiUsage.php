<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * صفّ استخدام ذكاء اصطناعي قابل للاستعلام — للرصد التشغيلي وفرض الحدود. لا يحوي
 * محتوى حسّاساً (لا نصوص/تلقينات/مخرجات): فقط مَن/أي مزوّد/أي عملية/توكِنات
 * مقدّرة/تكلفة مقدّرة/وقت. التوكِنات والتكلفة تقديرية (من حجم الإدخال/الإخراج).
 *
 * لا عمود updated_at — السجلّ غير قابل للتعديل بطبيعته (append-only).
 */
class AiUsage extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'provider',
        'action',
        'source',
        'tokens',
        'estimated_cost',
    ];

    protected $casts = [
        'tokens' => 'integer',
        'estimated_cost' => 'decimal:6',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
