<?php

declare(strict_types=1);

return [
    'group' => [
        'created' => 'Group created.',
        'updated' => 'Group updated.',
        'deleted' => 'Group deleted.',
        'default_locked' => 'The default group "Site subscribers" cannot be deleted.',
    ],

    'contact' => [
        'created' => 'Contact added.',
        'updated' => 'Contact updated.',
        'deleted' => 'Contact deleted.',
        'invalid_phone' => 'Invalid phone number. Enter a full international number in E.164 format (e.g. +9627XXXXXXXX).',
        'duplicate_phone' => 'This phone number is already registered.',
    ],

    'import' => [
        'done' => 'Import finished.',
        'unreadable' => 'Could not read the file. Make sure it is a valid CSV or XLSX.',
        'name_missing' => 'Name is missing in this row.',
        'row_limit' => 'Row limit reached (:max). Remaining rows were stopped.',
    ],

    'public' => [
        'subscribed' => 'You have subscribed successfully. You will receive the latest news on WhatsApp.',
        'unsubscribed' => 'You have unsubscribed. You will no longer receive messages.',
        'invalid_phone' => 'Please enter a valid international phone number (e.g. +9627XXXXXXXX).',
        'unavailable' => 'The subscription service is currently unavailable.',
    ],

    'campaign' => [
        'created' => 'Campaign created.',
        'deleted' => 'Campaign deleted.',
        'sending' => 'Campaign sending has started.',
        'cancelled' => 'Campaign cancelled.',
        'test_sent' => 'Test message sent.',
        'test_failed' => 'Failed to send the test message.',
        'duplicate' => 'An identical campaign was just created. Avoid accidental duplicates.',
        'empty_promo' => 'A promotional message needs at least text or media.',
        'media_required' => 'Choose the media file (image/video).',
        'not_sendable' => 'This campaign cannot be sent in its current state.',
        'not_cancellable' => 'This campaign cannot be cancelled in its current state.',
        'not_configured' => 'WhatsApp settings are incomplete. Enable the integration and set the instance and token.',
        'sending_locked' => 'A campaign cannot be deleted while it is sending.',
    ],
];
