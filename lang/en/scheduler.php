<?php

declare(strict_types=1);

return [
    'updated' => 'Task updated successfully.',
    'run_success' => 'Task executed successfully.',
    'run_failed' => 'Task execution failed.',
    'cooldown' => 'Task ran recently — wait before running again.',
    'not_runnable' => 'This task cannot be run manually.',
    'not_found' => 'Task not found.',

    'frequency' => [
        'daily' => 'Daily (midnight)',
        'dailyAt0100' => 'Daily 01:00',
        'dailyAt0130' => 'Daily 01:30',
        'dailyAt0200' => 'Daily 02:00',
        'dailyAt0300' => 'Daily 03:00',
        'dailyAt0400' => 'Daily 04:00',
        'dailyAt0500' => 'Daily 05:00',
        'dailyAt0530' => 'Daily 05:30',
        'everyMinute' => 'Every minute',
        'custom' => 'Custom',
    ],

    'tasks' => [
        'articles_publish_due' => [
            'name' => 'Publish scheduled articles',
            'description' => 'Publishes articles whose scheduled time is due (scheduled → published).',
        ],
        'activity_log_cleanup' => [
            'name' => 'Activity log cleanup',
            'description' => 'Delete activity records older than the retention window (365 days).',
        ],
        'backups_run' => [
            'name' => 'Run backup',
            'description' => 'Create a full system backup.',
        ],
        'backups_cleanup' => [
            'name' => 'Backups cleanup',
            'description' => 'Delete old backups per the retention policy.',
        ],
        'backups_monitor' => [
            'name' => 'Backups monitor',
            'description' => 'Verify backup health and freshness.',
        ],
        'password_reset_cleanup' => [
            'name' => 'Password reset cleanup',
            'description' => 'Delete expired password reset tokens.',
        ],
        'queue_failed_prune' => [
            'name' => 'Failed jobs prune',
            'description' => 'Delete failed jobs older than 7 days from failed_jobs.',
        ],
        'queue_batches_prune' => [
            'name' => 'Queue batches prune',
            'description' => 'Delete finished batch records older than 48 hours.',
        ],
        'sanctum_tokens_prune' => [
            'name' => 'Sanctum tokens prune',
            'description' => 'Delete expired access tokens (> 24 hours).',
        ],
    ],

    'activity' => [
        'updated' => 'Scheduled task updated',
        'manual_run' => 'Scheduled task manual run',
    ],
];
