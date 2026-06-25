<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Whatsapp;

use App\Actions\Admin\Whatsapp\CreateWhatsappContactAction;
use App\Actions\Admin\Whatsapp\DeleteWhatsappContactAction;
use App\Actions\Admin\Whatsapp\ExportWhatsappContactsAction;
use App\Actions\Admin\Whatsapp\ImportWhatsappContactsAction;
use App\Actions\Admin\Whatsapp\ListWhatsappContactsAction;
use App\Actions\Admin\Whatsapp\UpdateWhatsappContactAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Whatsapp\ImportWhatsappContactsRequest;
use App\Http\Requests\Admin\Whatsapp\StoreWhatsappContactRequest;
use App\Http\Requests\Admin\Whatsapp\UpdateWhatsappContactRequest;
use App\Models\WhatsappContact;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WhatsappContactController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListWhatsappContactsAction)->handle();
    }

    public function store(StoreWhatsappContactRequest $request): JsonResponse
    {
        return (new CreateWhatsappContactAction)->handle($request->validated());
    }

    public function update(UpdateWhatsappContactRequest $request, WhatsappContact $whatsappContact): JsonResponse
    {
        return (new UpdateWhatsappContactAction)->handle($whatsappContact, $request->validated());
    }

    public function destroy(WhatsappContact $whatsappContact): JsonResponse
    {
        return (new DeleteWhatsappContactAction)->handle($whatsappContact);
    }

    public function import(ImportWhatsappContactsRequest $request): JsonResponse
    {
        return (new ImportWhatsappContactsAction)->handle($request->validated());
    }

    public function export(): BinaryFileResponse
    {
        return (new ExportWhatsappContactsAction)->handle();
    }
}
