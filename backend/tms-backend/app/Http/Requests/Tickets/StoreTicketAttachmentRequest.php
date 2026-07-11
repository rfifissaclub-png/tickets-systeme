<?php

namespace App\Http\Requests\Tickets;

use App\Http\Requests\ApiFormRequest;
use App\Services\Attachments\FileUploadService;

class StoreTicketAttachmentRequest extends ApiFormRequest
{
    /**
     * Needs the route-bound Ticket (participant/permission + Closed check),
     * done in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.FileUploadService::MAX_SIZE_KB,
                // Checked against the real file content (fileinfo), not
                // just the client-supplied extension or Content-Type header.
                'mimetypes:'.implode(',', FileUploadService::ALLOWED_MIME_TYPES),
            ],
        ];
    }
}
