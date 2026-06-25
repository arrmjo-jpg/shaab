<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Writer notification strings (database notifications — P1.2)
|--------------------------------------------------------------------------
*/

return [
    // Display message — composed at read time in NotificationResource.
    'content' => [
        'published' => 'Your :type ":title" has been published.',
        'rejected' => 'Your :type ":title" has been rejected.',
    ],

    'type' => [
        'article' => 'article',
        'reel' => 'reel',
        'video' => 'video',
    ],

    // Route messages
    'marked_read' => 'Notification marked as read.',
    'marked_all_read' => 'All notifications marked as read.',
    'not_found' => 'Notification not found.',

    // Writer-request notifications (P1.4) — bell message (database).
    'writer_request' => [
        'approved' => 'Your request to become a writer was approved — you can now create and submit content.',
        'rejected' => 'Your request to become a writer was rejected.',
    ],

    // Writer-request notifications — mail copy.
    'writer_request_mail' => [
        'greeting' => 'Hello :name,',
        'approved' => [
            'subject' => 'Your writer request was approved',
            'line1' => 'We are glad to let you know your request to become a writer was approved.',
            'line2' => 'You can now create content and submit it for review from your account.',
        ],
        'rejected' => [
            'subject' => 'About your writer request',
            'line1' => 'We are sorry to let you know your writer request was not approved at this time.',
            'line2' => 'You may contact the administration for more details.',
        ],
    ],
];
