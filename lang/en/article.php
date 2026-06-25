<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Article domain strings
|--------------------------------------------------------------------------
*/

return [
    'type' => [
        'news' => 'News',
        'opinion' => 'Opinion',
        'live' => 'Live Coverage',
    ],

    'status' => [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'in_review' => 'In Review',
        'scheduled' => 'Scheduled',
        'published' => 'Published',
        'rejected' => 'Rejected',
        'archived' => 'Archived',
    ],

    'event_status' => [
        'scheduled' => 'Scheduled',
        'live' => 'Live',
        'paused' => 'Paused',
        'completed' => 'Completed',
    ],

    'created' => 'The article was created successfully.',
    'updated' => 'The article was updated successfully.',
    'deleted' => 'The article was deleted successfully.',
    'restored' => 'The article was restored successfully.',
    'force_deleted' => 'The article was permanently deleted.',
    'not_trashed' => 'This article is not deleted.',
    'breaking_cleared' => ':count breaking news item(s) were cleared.',
    'pinned_cleared' => ':count pinned article(s) were unpinned.',
    'status_changed' => 'The article status was changed successfully.',
    'invalid_content' => 'The editor content contains disallowed nodes or attributes.',
    'invalid_feed' => 'Invalid feed kind or locale.',
    'invalid_locale' => 'The requested locale is not supported.',
    'not_found' => 'The article was not found.',

    // Publishing workflow
    'invalid_transition' => 'This status transition is not allowed.',
    'writer_transition_forbidden' => 'A writer may only submit their own content for review.',
    'schedule_requires_future_date' => 'Scheduling requires a future publish date.',

    // Category assignment rules (ADR A3)
    'primary_category_not_found' => 'The primary category was not found.',
    'primary_category_locale_mismatch' => 'The primary category must be in the same locale as the article.',
    'secondary_category_not_found' => 'One of the secondary categories was not found.',
    'secondary_category_locale_mismatch' => 'The secondary categories must be in the same locale as the article.',
    'too_many_secondary_categories' => 'The maximum number of secondary categories is 3.',
    'primary_in_secondary' => 'The primary category cannot be among the secondary categories.',
    'primary_not_deepest' => 'The primary category must be the deepest among the assigned categories.',
    'category_scope_mismatch' => 'The category scope does not match the article type.',
    'opinion_single_category' => 'An opinion article accepts only one category with no secondary categories.',

    // Authorization / writer workflow (locked decisions)
    'cannot_create' => 'You do not have permission to create this content.',
    'writer_author_forbidden' => 'A writer cannot create content under another\'s name; it is automatically attributed to them.',
    'writer_cannot_create_live' => 'Writers are not allowed to create live coverage.',
    'writer_cannot_edit_others' => 'You cannot edit content you do not own.',
    'writer_edit_state_locked' => 'A writer can only edit their own drafts or rejected items.',
    'opinion_author_must_be_writer' => 'The author of an opinion article must be a user with the writer role.',
    'author_not_found' => 'The specified author was not found.',

    // Media attachment (attach-on-save)
    'media' => [
        'single_cover' => 'More than one cover image cannot be set.',
    ],
];
