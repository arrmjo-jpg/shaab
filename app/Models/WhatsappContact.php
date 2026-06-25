<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WhatsappContactStatus;
use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * جهة اتصال واتساب — اسم + رقم دولي كامل E.164 في حقل واحد (phone، فريد) + مجموعاتها.
 * التدقيق بلا PII: الهاتف وتوكن إلغاء الاشتراك مستثنيان من auditAttributes عمداً
 * (نهج Comment: «لا PII الزائر» — model-audit skill).
 */
class WhatsappContact extends Model
{
    use AuditsChanges;
    use SoftDeletes;

    protected string $auditLogName = 'whatsapp_contact';

    /** @var array<int,string> بلا phone (PII) وبلا unsubscribe_token (سرّي). */
    protected array $auditAttributes = ['name', 'status', 'source'];

    protected $fillable = ['name', 'phone', 'status', 'source', 'unsubscribe_token'];

    /** @var array<int,string> التوكن لا يظهر في أي تسلسل افتراضي. */
    protected $hidden = ['unsubscribe_token'];

    protected function casts(): array
    {
        return [
            'status' => WhatsappContactStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $contact): void {
            if (empty($contact->unsubscribe_token)) {
                $contact->unsubscribe_token = Str::random(48);
            }
        });
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(WhatsappGroup::class, 'whatsapp_contact_group');
    }

    public function scopeSubscribed(Builder $query): Builder
    {
        return $query->where('status', WhatsappContactStatus::Subscribed->value);
    }
}
