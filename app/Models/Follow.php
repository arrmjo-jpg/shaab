<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FollowableType;
use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * متابعة مستخدمٍ لكيان رياضيّ خارجيّ (فريق/بطولة/لاعب/مباراة) من 365.
 * الهدف معرّف 365 (followable_id) لا علاقة محليّة. لا أسرار/PII ⇒ كل الحقول مُدقَّقة.
 */
class Follow extends Model
{
    use AuditsChanges;

    protected string $auditLogName = 'follow';

    /** @var array<int,string> لا أسرار — كلّها آمنة للتدقيق. */
    protected array $auditAttributes = ['user_id', 'followable_type', 'followable_id'];

    protected $fillable = ['user_id', 'followable_type', 'followable_id'];

    protected function casts(): array
    {
        return [
            'followable_type' => FollowableType::class,
            'followable_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfType(Builder $query, FollowableType $type): Builder
    {
        return $query->where('followable_type', $type->value);
    }
}
