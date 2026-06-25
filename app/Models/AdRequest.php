<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdRequestStatus;
use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * طلب إعلان من معلِن عامّ — intake مبيعات بدورة حياة (قالب WriterRequest). مستقلّ تماماً عن
 * ContactMessage. status هو مصدر «الجديد» (يقود Badge)؛ read_at seen فقط. الملاحظات سجلّ
 * (ad_request_notes) لا عمود مفرد.
 */
class AdRequest extends Model
{
    use AuditsChanges;
    use SoftDeletes;

    protected string $auditLogName = 'ad_request';

    /** @var array<int,string> لا PII: الحالة والمراجعة فقط. */
    protected array $auditAttributes = ['status', 'reviewed_by', 'reviewed_at'];

    protected $fillable = [
        'company_name',
        'contact_name',
        'email',
        'phone',
        'website',
        'ad_type',
        'description',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'status',
        'read_at',
        'reviewed_by',
        'reviewed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => AdRequestStatus::class,
            'read_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** ملاحظات داخليّة (الأحدث أوّلاً). */
    public function notes(): HasMany
    {
        return $this->hasMany(AdRequestNote::class)->latest();
    }
}
