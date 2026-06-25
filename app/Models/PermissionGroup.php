<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * مجموعة صلاحيات حقيقية (كيان). الصلاحيات تنتمي إليها عبر permission_group_id.
 */
class PermissionGroup extends Model
{
    use AuditsChanges;

    protected string $auditLogName = 'permission_group';

    /** @var array<int,string> */
    protected array $auditAttributes = [
        'slug', 'display_name', 'description', 'icon', 'sort_order', 'is_system',
    ];

    protected $fillable = [
        'slug',
        'display_name',
        'description',
        'icon',
        'sort_order',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_system' => 'boolean',
        ];
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }
}
