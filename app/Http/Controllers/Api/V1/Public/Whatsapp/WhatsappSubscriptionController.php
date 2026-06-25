<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Whatsapp;

use App\Actions\Public\Whatsapp\SubscribeWhatsappAction;
use App\Actions\Public\Whatsapp\UnsubscribeWhatsappAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Whatsapp\SubscribeWhatsappRequest;
use App\Http\Requests\Public\Whatsapp\UnsubscribeWhatsappRequest;
use Illuminate\Http\JsonResponse;

/**
 * اشتراك/إلغاء اشتراك عامّ في قائمة واتساب. الحماية (throttle) على المسار.
 */
class WhatsappSubscriptionController extends Controller
{
    public function subscribe(SubscribeWhatsappRequest $request): JsonResponse
    {
        return (new SubscribeWhatsappAction)->handle($request->validated());
    }

    public function unsubscribe(UnsubscribeWhatsappRequest $request): JsonResponse
    {
        return (new UnsubscribeWhatsappAction)->handle($request->validated());
    }
}
