<?php

namespace App\Http\Resources;

use App\Models\TicketAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately does not include a download URL: generating a presigned S3
 * URL is a per-request signing operation, not a static attribute, and doing
 * it for every item in a list invites long-lived link caching by clients.
 * Fetch it fresh from the dedicated download endpoint instead.
 *
 * @mixin TicketAttachment
 */
class TicketAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uploader' => new UserResource($this->whenLoaded('uploader')),
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'created_at' => $this->created_at,
        ];
    }
}
