<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Whatsapp;

use App\Actions\Admin\Whatsapp\CreateWhatsappGroupAction;
use App\Actions\Admin\Whatsapp\DeleteWhatsappGroupAction;
use App\Actions\Admin\Whatsapp\ListWhatsappGroupsAction;
use App\Actions\Admin\Whatsapp\UpdateWhatsappGroupAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Whatsapp\StoreWhatsappGroupRequest;
use App\Http\Requests\Admin\Whatsapp\UpdateWhatsappGroupRequest;
use App\Models\WhatsappGroup;
use Illuminate\Http\JsonResponse;

class WhatsappGroupController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListWhatsappGroupsAction)->handle();
    }

    public function store(StoreWhatsappGroupRequest $request): JsonResponse
    {
        return (new CreateWhatsappGroupAction)->handle($request->validated());
    }

    public function update(UpdateWhatsappGroupRequest $request, WhatsappGroup $whatsappGroup): JsonResponse
    {
        return (new UpdateWhatsappGroupAction)->handle($whatsappGroup, $request->validated());
    }

    public function destroy(WhatsappGroup $whatsappGroup): JsonResponse
    {
        return (new DeleteWhatsappGroupAction)->handle($whatsappGroup);
    }
}
