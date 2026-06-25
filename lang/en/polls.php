<?php

declare(strict_types=1);

return [
    'poll' => [
        'created' => 'Poll created.',
        'updated' => 'Poll updated.',
        'deleted' => 'Poll deleted.',
        'restored' => 'Poll restored.',
        'force_deleted' => 'Poll permanently deleted.',
        'status_changed' => 'Poll activation changed.',
    ],

    'option' => [
        'has_votes' => 'Cannot delete an option that already has votes.',
    ],

    'public' => [
        'not_found' => 'Poll not found.',
        'closed' => 'This poll is not open for voting.',
        'not_authenticated' => 'You must be signed in to vote in this poll.',
        'single_only' => 'This poll allows only one choice.',
        'invalid_options' => 'One or more selected options are invalid.',
        'already_voted' => 'You have already voted in this poll.',
        'accepted' => 'Your vote has been recorded.',
    ],

    // واجهة الودجة العامة (تُمرَّر للـ JS كـ JSON في الشِل — مترجمة حسب لغة الصفحة).
    'widget' => [
        'loading' => 'Loading poll…',
        'noscript' => 'Enable JavaScript to view and vote in this poll.',
        'vote' => 'Vote',
        'results' => 'Results',
        'total_votes' => 'Total votes',
        'closed' => 'Voting is closed.',
        'already_voted' => 'You have already voted.',
        'thanks' => 'Thanks for voting!',
        'error' => 'Something went wrong. Please try again.',
        'choose_one' => 'Choose one option',
        'choose_multiple' => 'Choose one or more options',
    ],
];
