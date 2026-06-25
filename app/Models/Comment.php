<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommentStatus;
use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * تعليق على محتوى (polymorphic) — أساس نظام التعليقات والإشراف. الإشراف
 * (اعتماد/رفض/حذف) لاحق؛ هذه الشريحة = النطاق + القراءة فقط.
 */
class Comment extends Model
{
    use AuditsChanges;
    use SoftDeletes;

    protected string $auditLogName = 'comment';

    /** @var array<int,string> يُدقَّق الإشراف + المتن (لا PII الزائر). */
    protected array $auditAttributes = ['body', 'status', 'parent_id'];

    protected $fillable = [
        'commentable_type', 'commentable_id', 'user_id', 'parent_id',
        'author_name', 'author_email', 'body', 'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommentStatus::class,
        ];
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
