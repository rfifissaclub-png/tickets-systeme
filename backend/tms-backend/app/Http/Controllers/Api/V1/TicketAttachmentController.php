<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreTicketAttachmentRequest;
use App\Http\Resources\TicketAttachmentResource;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\Attachments\FileUploadService;
use App\Services\Tenancy\TenantContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TicketAttachmentController extends Controller
{
    public function __construct(
        private readonly FileUploadService $fileUploadService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Ticket $ticket): JsonResponse
    {
        $this->authorize('viewAny', [TicketAttachment::class, $ticket]);

        $attachments = $this->tenantContext->runScoped(
            'platform-wide attachment lookup (super admin)',
            fn () => $ticket->attachments()->with('uploader')->orderBy('created_at')->get(),
        );

        return ApiResponse::success(TicketAttachmentResource::collection($attachments));
    }

    public function store(StoreTicketAttachmentRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('create', [TicketAttachment::class, $ticket]);

        $attachment = $this->tenantContext->runScoped(
            'platform-wide attachment upload (super admin)',
            fn () => $this->fileUploadService->store($request->file('file'), $ticket, $request->user())->load('uploader'),
        );

        return ApiResponse::success(
            data: new TicketAttachmentResource($attachment),
            message: __('messages.attachments.uploaded'),
            status: 201,
        );
    }

    public function download(TicketAttachment $attachment): JsonResponse
    {
        $this->authorize('download', $attachment);

        // No bypass needed: temporaryDownloadUrl() only reads already-loaded
        // scalar attributes (disk, stored_path) to build an S3 URL — unlike
        // every other action here, it runs no query of its own.
        return ApiResponse::success(['url' => $attachment->temporaryDownloadUrl()]);
    }

    public function destroy(TicketAttachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);

        $this->tenantContext->runScoped(
            'platform-wide attachment deletion (super admin)',
            fn () => $this->fileUploadService->delete($attachment),
        );

        return ApiResponse::success(message: __('messages.attachments.deleted'));
    }
}
