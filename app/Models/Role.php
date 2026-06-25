<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Support\Config;

/**
 * نموذج الدور الموسّع — يربط علاقة users صراحةً بنموذج المستخدم في التطبيق.
 *
 * يُصلح فشل withCount('users') حين يتعذّر على Spatie حلّ نموذج
 * الحارس (getModelForGuard) أثناء بناء الاستعلام (لا instance بـ guard_name).
 * مسجَّل عبر config/permission.php → models.role.
 */
class Role extends SpatieRole
{
    use AuditsChanges;

    protected string $auditLogName = 'role';

    /** @var array<int,string> */
    protected array $auditAttributes = ['name', 'display_name', 'description'];

    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            Config::modelHasRolesTable(),
            app(PermissionRegistrar::class)->pivotRole,
            Config::morphKey()
        );
    }
}
