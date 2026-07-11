<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreTicketAttachmentRequest;
use App\Http\Resources\TicketAttachmentResource;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\Attachments\FileUploadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TicketAttachmentController extends Controller
{
    public function __construct(private readonly FileUploadService $fileUploadService) {}

    public function index(Ticket $ticket): JsonResponse
    {
        $this->authorize('viewAny', [TicketAttachment::class, $ticket]);

        $attachments = $ticket->attachments()->with('uploader')->orderBy('created_at')->get();

        return ApiResponse::success(TicketAttachmentResource::collection($attachments));
    }

    public function store(StoreTicketAttachmentRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('create', [TicketAttachment::class, $ticket]);

        $attachment = $this->fileUploadService->store($request->file('file'), $ticket, $request->user());

        return ApiResponse::success(
            data: new TicketAttachmentResource($attachment->load('uploader')),
            message: __('messages.attachments.uploaded'),
            status: 201,
        );
    }

    public function download(TicketAttachment $attachment): JsonResponse
    {
        $this->authorize('download', $attachment);

        return ApiResponse::success(['url' => $attachment->temporaryDownloadUrl()]);
    }

    public function destroy(TicketAttachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);

        $this->fileUploadService->delete($attachment);

        return ApiResponse::success(message: __('messages.attachments.deleted'));
    }
}
