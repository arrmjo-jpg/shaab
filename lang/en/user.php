<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| User domain strings
|--------------------------------------------------------------------------
*/

return [
    'status' => [
        'active' => 'Active',
        'suspended' => 'Suspended',
        'banned' => 'Banned',
    ],

    // ─── User management module messages ─────────────────────────────────
    'created' => 'The user was created successfully.',
    'updated' => 'The user details were updated successfully.',
    'deleted' => 'The user was deleted successfully.',
    'status_updated' => 'The user status was updated successfully.',
    'cannot_delete_self' => 'You cannot delete your own account.',
    'cannot_delete_super_admin' => 'The super admin account cannot be deleted.',
    'cannot_change_own_status' => 'You cannot change the status of your own account.',
    'cannot_remove_own_admin_roles' => 'You cannot remove all of your administrative roles from your account.',
    'cannot_grant_super_admin' => 'The super admin role can only be granted by a super admin.',
    'cannot_modify_super_admin' => 'A super admin account can only be modified by a super admin.',
    'restored' => 'The user was restored successfully.',
    'not_deleted' => 'The user is not deleted.',
    'password_reset_sent' => 'A password reset link has been sent to the user\'s email.',
    'password_reset_failed' => 'Failed to send the password reset link.',
    'avatar_uploaded' => 'The avatar was uploaded successfully.',
];
