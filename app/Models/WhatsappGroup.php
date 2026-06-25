<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * مجموعة واتساب — تجميع بسيط لجهات الاتصال (اسم + وصف اختياري). المجموعة الافتراضية
 * الوحيدة «مشتركو الموقع» هي وجهة اشتراك الموقع التلقائية (API/Box).
 */
class WhatsappGroup extends Model
{
    use AuditsChanges;
    use SoftDeletes;

    protected string $auditLogName = 'whatsapp_group';

    /** @var array<int,string> */
    protected array $auditAttributes = ['name', 'description', 'is_default'];

    protected $fillable = ['name', 'description', 'is_default'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(WhatsappContact::class, 'whatsapp_contact_group');
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }
}
