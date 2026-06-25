<?php

declare(strict_types=1);

return [
    'zone' => [
        'created' => 'Ad zone created.',
        'updated' => 'Ad zone updated.',
        'deleted' => 'Ad zone deleted.',
        'has_placements' => 'Cannot delete a zone with attached ads — detach them first.',
    ],

    'campaign' => [
        'created' => 'Campaign created.',
        'updated' => 'Campaign updated.',
        'deleted' => 'Campaign deleted.',
        'restored' => 'Campaign restored.',
        'force_deleted' => 'Campaign permanently deleted.',
        'status_changed' => 'Campaign status changed.',
        'invalid_transition' => 'That status transition is not allowed.',
        'window_expired' => 'The campaign window has ended — cannot activate.',
        'no_creative' => 'Add at least one active creative to the campaign before publishing.',
        'creative_not_renderable' => 'The creative is not renderable: an image creative needs a file, an HTML creative needs content.',
        'no_placement' => 'Link the creative to an ad zone via an active placement before publishing.',
        'zone_inactive' => 'The linked ad zone is inactive — activate it or pick an active zone before publishing.',
        'no_start_date' => 'Set a start date for the campaign before publishing.',
        'bad_dates' => 'The end date is before the start date — fix the campaign dates before publishing.',
    ],

    'creative' => [
        'created' => 'Creative created.',
        'updated' => 'Creative updated.',
        'deleted' => 'Creative deleted.',
        'restored' => 'Creative restored.',
        'force_deleted' => 'Creative permanently deleted.',
        'media_required' => 'Media is required for an image/video creative.',
        'html_required' => 'HTML code is required for an HTML creative.',
        'invalid_landing_url' => 'Invalid landing URL — must be http(s).',
        'video_not_enabled' => 'Video creatives are not enabled in this phase.',
    ],

    'placement' => [
        'attached' => 'Creative attached to the zone.',
        'updated' => 'Placement updated.',
        'detached' => 'Creative detached from the zone.',
        'incompatible_type' => 'Creative type is not compatible with this zone type.',
        'duplicate' => 'This creative is already attached to this zone.',
    ],

    'serve' => [
        'no_ad' => 'No ad available for this zone.',
        'unknown_zone' => 'Unknown ad zone.',
    ],

    'tracking' => [
        'invalid_token' => 'Invalid or expired tracking token.',
        'accepted' => 'Recorded.',
        'click_unavailable' => 'Ad destination unavailable.',
    ],
];
