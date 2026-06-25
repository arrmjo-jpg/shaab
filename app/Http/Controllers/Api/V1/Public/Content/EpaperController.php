<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Content;

use App\Actions\Public\Content\ListPublicEpapersAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class EpaperController extends Controller
{
    /** Published, public-access digital newspaper (PDF) issues for the locale. */
    public function index(string $locale): JsonResponse
    {
        return (new ListPublicEpapersAction)->handle($locale);
    }
}
