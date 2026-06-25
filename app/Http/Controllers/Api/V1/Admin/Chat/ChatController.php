<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Chat;

use App\Actions\Admin\Chat\CreateConversationAction;
use App\Actions\Admin\Chat\DeleteMessageAction;
use App\Actions\Admin\Chat\ListChatContactsAction;
use App\Actions\Admin\Chat\ListConversationsAction;
use App\Actions\Admin\Chat\ListMessagesAction;
use App\Actions\Admin\Chat\MarkConversationReadAction;
use App\Actions\Admin\Chat\SendMessageAction;
use App\Actions\Admin\Chat\UpdateMessageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Chat\SendMessageRequest;
use App\Http\Requests\Admin\Chat\StoreConversationRequest;
use App\Http\Requests\Admin\Chat\UpdateMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * شات داخليّ بين المدراء — متاح لكل أدمن مُصادَق (لا صلاحية نظام؛ الوصول row-level
 * بالعضوية داخل الـ Actions). تحكّم رفيع — كل المنطق في طبقة الـ Actions.
 */
class ChatController extends Controller
{
    public function conversations(Request $request): JsonResponse
    {
        return (new ListConversationsAction)->handle($request->user());
    }

    public function contacts(Request $request): JsonResponse
    {
        return (new ListChatContactsAction)->handle($request->user(), $request);
    }

    public function storeConversation(StoreConversationRequest $request): JsonResponse
    {
        return (new CreateConversationAction)->handle($request->user(), $request->validated());
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        return (new ListMessagesAction)->handle($request->user(), $conversation, $request);
    }

    public function send(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        return (new SendMessageAction)->handle($request->user(), $conversation, $request->validated());
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        return (new MarkConversationReadAction)->handle($request->user(), $conversation);
    }

    public function updateMessage(UpdateMessageRequest $request, Message $message): JsonResponse
    {
        return (new UpdateMessageAction)->handle($request->user(), $message, $request->validated());
    }

    public function deleteMessage(Request $request, Message $message): JsonResponse
    {
        return (new DeleteMessageAction)->handle($request->user(), $message);
    }
}
