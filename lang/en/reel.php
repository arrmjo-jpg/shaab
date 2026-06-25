<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Reels domain strings
|--------------------------------------------------------------------------
*/

return [
    'status' => [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'in_review' => 'In Review',
        'scheduled' => 'Scheduled',
        'published' => 'Published',
        'rejected' => 'Rejected',
        'archived' => 'Archived',
    ],

    'created' => 'The reel was created successfully.',
    'updated' => 'The reel was updated successfully.',
    'deleted' => 'The reel was deleted successfully.',
    'restored' => 'The reel was restored successfully.',
    'force_deleted' => 'The reel was permanently deleted.',
    'status_changed' => 'The reel status was changed successfully.',
    'not_trashed' => 'This reel is not deleted.',
    'not_found' => 'The reel was not found.',
    'forbidden_transition' => 'You do not have permission to perform this transition.',
    'schedule_future' => 'The scheduled time must be in the future.',
    'media_not_ready' => 'Publishing or scheduling is not possible before the reel video is uploaded and its processing is complete.',

    // Writer authorization (ReelAuthorizationGuard)
    'cannot_create' => 'You do not have permission to create this content.',
    'writer_author_forbidden' => 'A writer cannot create content under another\'s name; it is automatically attributed to them.',
    'author_not_found' => 'The specified author was not found.',
    'writer_cannot_edit_others' => 'You cannot edit content you do not own.',

    // Transition workflow (ReelWorkflowGuard)
    'invalid_transition' => 'This status transition is not allowed.',
    'writer_transition_forbidden' => 'A writer may only submit their own content for review.',
];
