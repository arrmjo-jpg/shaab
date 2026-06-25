<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Ad;

use App\Actions\Admin\Ad\AddAdRequestNoteAction;
use App\Actions\Admin\Ad\DeleteAdRequestAction;
use App\Actions\Admin\Ad\ListAdRequestsAction;
use App\Actions\Admin\Ad\MarkAdRequestReadAction;
use App\Actions\Admin\Ad\UpdateAdRequestStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Ad\AddAdRequestNoteRequest;
use App\Http\Requests\Admin\Ad\UpdateAdRequestStatusRequest;
use App\Http\Resources\Admin\Ad\AdRequestResource;
use App\Models\AdRequest;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdRequestController extends Controller
{
    /** قائمة طلبات الإعلان — ad-requests.view. */
    public function index(): JsonResponse
    {
        return (new ListAdRequestsAction)->handle();
    }

    /** تفاصيل الطلب + ملاحظاته — ad-requests.view. */
    public function show(AdRequest $adRequest): JsonResponse
    {
        return ApiResponse::success(
            data: new AdRequestResource($adRequest->load(['reviewedBy', 'notes.user'])),
        );
    }

    /** تغيير الحالة — ad-requests.review. */
    public function updateStatus(UpdateAdRequestStatusRequest $request, AdRequest $adRequest): JsonResponse
    {
        return (new UpdateAdRequestStatusAction)->handle(
            $adRequest,
            $request->validated()['status'],
            (int) $request->user()->id,
        );
    }

    /** Mark as Read — ad-requests.view. */
    public function markRead(AdRequest $adRequest): JsonResponse
    {
        return (new MarkAdRequestReadAction)->handle($adRequest);
    }

    /** إضافة ملاحظة داخليّة — ad-requests.review. */
    public function addNote(AddAdRequestNoteRequest $request, AdRequest $adRequest): JsonResponse
    {
        return (new AddAdRequestNoteAction)->handle(
            $adRequest,
            $request->validated()['body'],
            (int) $request->user()->id,
        );
    }

    /** حذف ناعم — ad-requests.delete. */
    public function destroy(AdRequest $adRequest): JsonResponse
    {
        return (new DeleteAdRequestAction)->handle($adRequest);
    }

    /**
     * تنزيل المرفق (صورة/ZIP) — ad-requests.view. مرفق خاصّ يُخدَم **تنزيلًا فقط**
     * (Content-Disposition: attachment)؛ لا عرض/تنفيذ/فكّ ضغط لمحتواه.
     */
    public function downloadAttachment(AdRequest $adRequest): StreamedResponse
    {
        abort_if($adRequest->attachment_path === null, 404);
        abort_unless(Storage::disk('local')->exists($adRequest->attachment_path), 404);

        return Storage::disk('local')->download(
            $adRequest->attachment_path,
            $adRequest->attachment_name ?? 'attachment',
        );
    }
}
