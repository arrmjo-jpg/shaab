<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Contact;

use App\Actions\Public\Contact\CreateContactMessageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Contact\StorePublicContactMessageRequest;
use Illuminate\Http\JsonResponse;

/**
 * استقبال رسائل «اتصل بنا» العامّة. الحماية على المسار (recaptcha:contact + throttle:public.contact).
 */
class ContactMessageController extends Controller
{
    public function store(StorePublicContactMessageRequest $request): JsonResponse
    {
        return (new CreateContactMessageAction)->handle($request->validated(), $request);
    }
}
