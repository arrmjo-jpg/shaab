<?php

declare(strict_types=1);

return [
    'type' => [
        'inquiry' => 'Inquiry',
        'complaint' => 'Complaint',
        'suggestion' => 'Suggestion',
        'other' => 'Other',
    ],
    'status' => [
        'new' => 'New',
        'in_review' => 'In review',
        'replied' => 'Replied',
        'closed' => 'Closed',
    ],
    'created' => 'Your message has been received. We will get back to you shortly.',
    'status_changed' => 'Status updated.',
    'deleted' => 'Message deleted.',
    'replied' => 'Reply sent successfully.',
    'reply_mail_failed' => 'Failed to send the email; the reply was not marked as sent. Please try again.',
    'reply_mail' => [
        'subject' => 'Reply to your message: :subject',
    ],
    'notification' => [
        'subject' => 'New contact message',
        'line' => 'A new :type message was received from :name.',
    ],
];
