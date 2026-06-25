<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Models\WhatsappContact;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class DeleteWhatsappContactAction
{
    public function handle(WhatsappContact $contact): JsonResponse
    {
        $contact->delete(); // ناعم

        return ApiResponse::success(__('whatsapp.contact.deleted'));
    }
}
