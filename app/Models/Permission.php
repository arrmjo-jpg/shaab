<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * نموذج الصلاحية الموسّع — يضيف الانتماء لمجموعة صلاحيات حقيقية.
 * مسجَّل عبر config/permission.php → models.permission.
 */
class Permission extends SpatiePermission
{
    use AuditsChanges;

    protected string $auditLogName = 'permission';

    /** @var array<int,string> */
    protected array $auditAttributes = ['name', 'display_name', 'group', 'description'];

    public function permissionGroup(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class);
    }
}
