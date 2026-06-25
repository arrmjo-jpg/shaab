<?php

declare(strict_types=1);

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
    'visibility' => [
        'public' => 'Public',
        'unlisted' => 'Unlisted',
        'private' => 'Private',
    ],
    'source' => [
        'unsupported' => 'Unsupported video source. Allowed: YouTube, Vimeo, or a direct MP4 link from an allow-listed host.',
        'not_uploaded_video' => 'The selected asset is not a valid uploaded video.',
    ],
    'created' => 'Video created successfully.',
    'updated' => 'Video updated successfully.',
    'deleted' => 'Video deleted (recoverable).',
    'restored' => 'Video restored successfully.',
    'force_deleted' => 'Video permanently deleted.',
    'not_deleted' => 'The video is not deleted.',
    'status_changed' => 'Video status changed successfully.',
    'media_not_ready' => 'Cannot publish/schedule: the video media is not ready for playback yet.',
    'forbidden_transition' => 'You are not allowed to perform this transition.',
    'schedule_requires_date' => 'Scheduling requires a publish date/time.',
    'bulk_forbidden' => 'You are not allowed to perform this bulk action.',
    'bulk_done' => 'Processed :processed of :requested.',
    'not_found' => 'The video was not found.',
    'invalid_locale' => 'Unsupported locale.',
    'reprocess_queued' => 'Media reprocessing has been queued.',
    'reprocess_unavailable' => 'Reprocessing is only available for uploaded video media.',

    // Writer authorization (VideoAuthorizationGuard)
    'cannot_create' => 'You do not have permission to create this content.',
    'writer_author_forbidden' => 'A writer cannot create content under another\'s name; it is automatically attributed to them.',
    'author_not_found' => 'The specified author was not found.',
    'writer_cannot_edit_others' => 'You cannot edit content you do not own.',

    // Transition workflow (VideoWorkflowGuard)
    'invalid_transition' => 'This status transition is not allowed.',
    'writer_transition_forbidden' => 'A writer may only submit their own content for review.',
];
