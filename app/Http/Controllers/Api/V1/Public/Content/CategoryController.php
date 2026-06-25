<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Content;

use App\Actions\Public\Content\ListPublicCategoriesAction;
use App\Actions\Public\Content\ShowPublicCategoryAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(string $locale): JsonResponse
    {
        return (new ListPublicCategoriesAction)->handle($locale);
    }

    public function show(string $locale, string $slug): JsonResponse
    {
        return (new ShowPublicCategoryAction)->handle($locale, $slug);
    }
}
