<?php

declare(strict_types=1);

return [
    'followed' => 'Followed.',
    'unfollowed' => 'Unfollowed.',
    'unsupported_type' => 'Unsupported follow type.',

    // Follow notifications (Phase 2) — title + message per type. :home/:away/:player/:minute/:label.
    'notification' => [
        'match_reminder_title' => 'Match reminder',
        'match_reminder' => 'A match you follow starts soon: :home vs :away',
        'match_goal_title' => 'Goal',
        'match_goal' => 'Goal! :player :minute — :home vs :away',
        'match_yellow_card_title' => 'Yellow card',
        'match_yellow_card' => 'Yellow card: :player :minute — :home vs :away',
        'match_red_card_title' => 'Red card',
        'match_red_card' => 'Red card: :player :minute — :home vs :away',
        'match_event_title' => 'Match event',
        'match_event' => ':label :minute — :home vs :away',
    ],
];
