<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Epaper;

use App\Actions\Admin\Epaper\ShowNewspaperSettingsAction;
use App\Actions\Admin\Epaper\UpdateNewspaperSettingsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Epaper\UpdateNewspaperSettingsRequest;
use Illuminate\Http\JsonResponse;

class NewspaperSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return (new ShowNewspaperSettingsAction)->handle();
    }

    public function update(UpdateNewspaperSettingsRequest $request): JsonResponse
    {
        return (new UpdateNewspaperSettingsAction)->handle($request->validated());
    }
}
