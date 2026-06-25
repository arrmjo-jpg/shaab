<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Content;

use App\Actions\Public\Content\ListPublicLiveUpdatesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LiveUpdateController extends Controller
{
    /**
     * Public live timeline for an article by (locale + slug).
     * Returns 304 when the client's ETag still matches (polling-friendly).
     */
    public function index(Request $request, string $locale, string $slug): Response
    {
        return (new ListPublicLiveUpdatesAction)->handle($locale, $slug, $request);
    }
}
