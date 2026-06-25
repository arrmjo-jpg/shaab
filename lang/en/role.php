<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Role management module messages
|--------------------------------------------------------------------------
*/

return [
    'created' => 'The role was created successfully.',
    'updated' => 'The role was updated successfully.',
    'deleted' => 'The role was deleted successfully.',

    'cannot_delete_super_admin' => 'The super admin role cannot be deleted.',
    'cannot_delete_own_role' => 'You cannot delete a role that is assigned to you.',
    'super_admin_protected' => 'The super admin role is protected and its name or permissions cannot be modified.',
    'cannot_remove_own_critical_access' => 'You cannot revoke your own critical administrative permissions from a role assigned to you.',
];
