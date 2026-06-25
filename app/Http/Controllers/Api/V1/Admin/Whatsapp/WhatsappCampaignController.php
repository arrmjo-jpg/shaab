<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Whatsapp;

use App\Actions\Admin\Whatsapp\CancelWhatsappCampaignAction;
use App\Actions\Admin\Whatsapp\CountWhatsappRecipientsAction;
use App\Actions\Admin\Whatsapp\CreateWhatsappCampaignAction;
use App\Actions\Admin\Whatsapp\DeleteWhatsappCampaignAction;
use App\Actions\Admin\Whatsapp\ListWhatsappCampaignMessagesAction;
use App\Actions\Admin\Whatsapp\ListWhatsappCampaignsAction;
use App\Actions\Admin\Whatsapp\PreviewWhatsappCampaignAction;
use App\Actions\Admin\Whatsapp\SendWhatsappCampaignAction;
use App\Actions\Admin\Whatsapp\ShowWhatsappCampaignAction;
use App\Actions\Admin\Whatsapp\TestWhatsappCampaignAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Whatsapp\CountWhatsappRecipientsRequest;
use App\Http\Requests\Admin\Whatsapp\StoreWhatsappCampaignRequest;
use App\Http\Requests\Admin\Whatsapp\TestWhatsappCampaignRequest;
use App\Models\WhatsappCampaign;
use Illuminate\Http\JsonResponse;

class WhatsappCampaignController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListWhatsappCampaignsAction)->handle();
    }

    public function show(WhatsappCampaign $whatsappCampaign): JsonResponse
    {
        return (new ShowWhatsappCampaignAction)->handle($whatsappCampaign);
    }

    public function store(StoreWhatsappCampaignRequest $request): JsonResponse
    {
        return (new CreateWhatsappCampaignAction)->handle($request->validated(), (int) $request->user()->id);
    }

    public function destroy(WhatsappCampaign $whatsappCampaign): JsonResponse
    {
        return (new DeleteWhatsappCampaignAction)->handle($whatsappCampaign);
    }

    public function recipientsCount(CountWhatsappRecipientsRequest $request): JsonResponse
    {
        return (new CountWhatsappRecipientsAction)->handle($request->validated());
    }

    public function preview(WhatsappCampaign $whatsappCampaign): JsonResponse
    {
        return (new PreviewWhatsappCampaignAction)->handle($whatsappCampaign);
    }

    public function send(WhatsappCampaign $whatsappCampaign): JsonResponse
    {
        return (new SendWhatsappCampaignAction)->handle($whatsappCampaign);
    }

    public function test(TestWhatsappCampaignRequest $request, WhatsappCampaign $whatsappCampaign): JsonResponse
    {
        return (new TestWhatsappCampaignAction)->handle($whatsappCampaign, $request->validated());
    }

    public function cancel(WhatsappCampaign $whatsappCampaign): JsonResponse
    {
        return (new CancelWhatsappCampaignAction)->handle($whatsappCampaign);
    }

    public function messages(WhatsappCampaign $whatsappCampaign): JsonResponse
    {
        return (new ListWhatsappCampaignMessagesAction)->handle($whatsappCampaign);
    }
}
