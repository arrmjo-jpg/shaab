<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactMessageStatus;
use App\Enums\ContactMessageType;
use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * رسالة «اتصل بنا» من زائر عامّ — intake + إشراف (قالب WriterRequest + Comment).
 * status هو المصدر الوحيد لكون الرسالة «جديدة» (يقود Badge)؛ read_at مجرّد seen metadata.
 */
class ContactMessage extends Model
{
    use AuditsChanges;
    use SoftDeletes;

    protected string $auditLogName = 'contact_message';

    /** @var array<int,string> لا PII في التدقيق: الحالة والردّ فقط (لا email/phone/message/subject). */
    protected array $auditAttributes = ['status', 'replied_by', 'replied_at'];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'type',
        'message',
        'status',
        'read_at',
        'reply_body',
        'replied_at',
        'replied_by',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContactMessageType::class,
            'status' => ContactMessageStatus::class,
            'read_at' => 'datetime',
            'replied_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}
