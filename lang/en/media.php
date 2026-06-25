<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Media layer messages (settings assets)
|--------------------------------------------------------------------------
*/

return [
    'branding_uploaded' => 'The branding files were uploaded successfully.',
    'firebase_uploaded' => 'The Firebase credentials file was uploaded successfully.',
    'firebase_invalid' => 'The Firebase credentials file is invalid or is missing one of the required keys.',
    'deleted' => 'The asset was deleted successfully.',

    // Content media (P3)
    'uploaded' => 'The media item was uploaded successfully.',
    'not_found' => 'The media item was not found.',
    'invalid_file' => 'The file is not accepted for this collection.',
    'unsupported_embed' => 'The embed provider is not supported.',
    'embed_resolved' => 'The embed link was normalized successfully.',

    // Media studio (P9.1)
    'reordered' => 'The media order was updated successfully.',

    // Central library (P9.2)
    'asset_uploaded' => 'The asset was uploaded to the library successfully.',

    // External video (Wave 2)
    'external' => [
        'unsupported' => 'The video link is not supported or is invalid.',
        'added' => 'The external video was added successfully.',
    ],

    // Derivatives processing (Wave 4)
    'derivatives_queued' => 'Regeneration of derivatives was queued for :count asset(s).',
    'reprocess_queued' => 'Reprocessing of the asset was re-queued successfully.',
    'not_processable' => 'This asset does not accept reprocessing.',

    // Media Library Governance
    'metadata_updated' => 'The asset metadata was updated successfully.',
    'in_use' => 'This asset is used in :count place(s). Confirm force deletion to remove it.',
];
