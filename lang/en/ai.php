<?php

declare(strict_types=1);

return [
    'not_configured' => 'The AI assistant is not configured. Check the provider settings.',
    'unavailable' => 'The AI assistant is currently unavailable. Please try again shortly.',
    'context_required' => 'A title or body is required to provide enough context.',
    'quota_exceeded' => 'The AI usage/cost limit for this period has been reached. Try again later or contact an administrator.',

    // Deterministic fallbacks (no AI)
    'heuristic' => [
        'title_missing' => 'No title yet.',
        'title_short' => 'Title is short (:len chars) — aim for 50–60.',
        'title_long' => 'Title is long (:len chars) — may be truncated in search results.',
        'title_ok' => 'Title length looks good (:len chars).',
        'desc_missing' => 'No description/excerpt.',
        'desc_short' => 'Description is short (:len chars) — aim for 120–160.',
        'desc_long' => 'Description is long (:len chars) — may be truncated in search results.',
        'desc_ok' => 'Description length looks good (:len chars).',
        'sg_add_title' => 'Add a clear title that includes the focus keyword.',
        'sg_shorten_title' => 'Shorten the title to stay within 60 characters.',
        'sg_add_description' => 'Add a concise descriptive excerpt (120–160 chars).',
        'sg_lengthen_description' => 'Expand the description toward ~150 characters.',
        'sg_add_slug' => 'Set a short, clear slug.',
        'sg_add_tags' => 'Add relevant tags to improve indexing.',
        'sg_cover_keywords' => 'Cover the prominent keywords in your tags or description.',
    ],
];
