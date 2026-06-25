<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Content;

use App\Actions\Public\Content\BuildPublicHomepageAction;
use App\Actions\Public\Content\ListPublicFeedAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    /**
     * Single feed by kind (hero|breaking|header|editors_pick|story|latest).
     * Locale comes from the route prefix; the route constraint pre-validates kind.
     */
    public function show(Request $request, string $locale, string $kind): JsonResponse
    {
        $limit = $request->has('limit') ? (int) $request->query('limit') : null;

        return (new ListPublicFeedAction)->handle($locale, $kind, $limit);
    }

    /** Single-shot homepage aggregate — every zone + latest. */
    public function homepage(string $locale): JsonResponse
    {
        return (new BuildPublicHomepageAction)->handle($locale);
    }
}
