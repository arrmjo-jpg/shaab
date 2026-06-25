<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Category domain strings
|--------------------------------------------------------------------------
*/

return [
    'status' => [
        'active' => 'Active',
        'hidden' => 'Hidden',
    ],

    'scope' => [
        'news' => 'News',
        'opinion' => 'Opinion',
        'both' => 'All',
    ],

    'created' => 'The category was created successfully.',
    'updated' => 'The category was updated successfully.',
    'deleted' => 'The category was deleted successfully.',

    'self_parent' => 'A category cannot be its own parent.',
    'parent_not_found' => 'The parent category was not found.',
    'parent_locale_mismatch' => 'The parent category must be in the same locale as the category.',
    'circular_hierarchy' => 'A category cannot be moved into one of its own descendants (circular hierarchy).',
    'max_depth_exceeded' => 'The maximum category depth has been exceeded.',
    'locale_children_mismatch' => 'The locale cannot be changed while there are child categories in a different locale.',
    'has_children' => 'A category that has child categories cannot be deleted.',
    'not_found' => 'The category was not found.',
    'reordered' => 'The category order was changed successfully.',
    'bulk_updated' => ':count category(ies) were updated successfully.',
    'bulk_no_fields' => 'No field was specified for the bulk update.',
    'restored' => 'The category was restored successfully.',
    'force_deleted' => 'The category was permanently deleted.',
    'not_trashed' => 'This category is not deleted.',
    'restore_parent_first' => 'The parent category must be restored first.',
    'force_delete_has_articles' => 'Permanent deletion is not allowed: the category is still the primary category for news items. Change their primary section first.',
];
