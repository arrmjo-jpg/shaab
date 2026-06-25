<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API response messages
|--------------------------------------------------------------------------
| All user-facing messages are managed here — no strings hardcoded in code.
*/

return [
    'validation_failed' => 'The submitted data is invalid.',
    'unauthenticated' => 'You must be signed in to access this resource.',
    'forbidden' => 'You are not allowed to perform this action.',
    'not_found' => 'The requested resource was not found.',
    'method_not_allowed' => 'The request method is not allowed for this resource.',
    'throttled' => 'Too many attempts. Please try again later.',
    'unexpected_error' => 'An unexpected error occurred. Please try again later.',
    'bad_request' => 'The request is invalid.',
];
