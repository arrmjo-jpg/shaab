<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ملاحظة داخليّة مفردة على طلب إعلان (append-only، بتاريخ + كاتب). يحفظ تاريخ المتابعة
 * كاملاً دون كتابة فوق سابق.
 */
class AdRequestNote extends Model
{
    use AuditsChanges;

    protected string $auditLogName = 'ad_request_note';

    /** @var array<int,string> */
    protected array $auditAttributes = ['ad_request_id', 'body'];

    protected $fillable = ['ad_request_id', 'user_id', 'body'];

    public function adRequest(): BelongsTo
    {
        return $this->belongsTo(AdRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
