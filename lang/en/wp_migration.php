<?php

declare(strict_types=1);

return [
    'run_status' => [
        'draft' => 'Draft',
        'ready' => 'Ready',
        'running' => 'Running',
        'paused' => 'Paused',
        'stopping' => 'Stopping',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ],

    'item_status' => [
        'pending' => 'Pending',
        'queued' => 'Queued',
        'processing' => 'Processing',
        'partial' => 'Partial',
        'done' => 'Done',
        'failed' => 'Failed',
        'skipped' => 'Skipped',
    ],

    'media_status' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'done' => 'Done',
        'failed' => 'Failed',
        'skipped' => 'Skipped',
    ],

    'category_mode' => [
        'exclude' => 'Exclude',
        'news' => 'News',
        'articles' => 'Articles',
    ],

    'conflict_policy' => [
        'prefer_news' => 'Prefer News',
        'prefer_articles' => 'Prefer Articles',
        'exclude' => 'Exclude conflicted',
    ],

    'connection' => [
        'ok' => 'Connected to the WordPress source successfully.',
        'failed' => 'Could not connect to the database — check credentials and permissions.',
        'not_wordpress' => 'No WordPress installation detected on this connection.',
    ],

    'run' => [
        'created' => 'Migration run created.',
        'listed' => 'Migration runs.',
        'shown' => 'Migration run details.',
        'started' => 'Execution started.',
        'paused' => 'Execution paused.',
        'resumed' => 'Execution resumed.',
        'stopped' => 'Execution stopped.',
        'not_executable' => 'Cannot execute: a current approved preview and a conflict policy are required.',
        // ⚠️ TEMPORARY FEATURE — Quick Incremental Import — Remove before Production release.
        'never_approved' => 'Approve the mapping and policy once before using “import new only”.',
        'quick_disabled' => '“Import new only” shortcut is disabled (development only).',
        'author_missing' => 'Canonical author «كتاب الموقع» is missing — create it before execution.',
        'uploads_unreadable' => 'The media files path is not readable on the server.',
        'not_running' => 'The run is not running.',
        'not_resumable' => 'The run cannot be resumed from its current state.',
        'not_stoppable' => 'The run cannot be stopped from its current state.',
        'retry_queued' => 'Selected items were re-queued for another attempt.',
        'nothing_to_retry' => 'No matching items to retry.',
    ],

    'audit' => [
        'done' => 'Source audit complete.',
    ],

    'map' => [
        'saved' => 'Category mapping saved.',
        'target_required' => 'A target category is required for each mapped category.',
        'scope_mismatch' => 'The target category does not match the chosen content type.',
        'type_required' => 'Choose a content type (News or Articles) for each included category.',
    ],

    'taxonomy' => [
        'imported' => 'Taxonomy imported — categories created.',
        'id_conflict' => 'Cannot preserve original category IDs — some IDs are already taken by existing categories. Resolve the conflicting IDs before importing.',
    ],

    'preview' => [
        'generated' => 'Impact preview generated.',
        'approved' => 'Preview approved and conflict policy locked in.',
        'stale' => 'Preview is stale — regenerate it after the mapping change before approving.',
        'no_mappings' => 'Select and map at least one category before generating the preview.',
    ],
];
