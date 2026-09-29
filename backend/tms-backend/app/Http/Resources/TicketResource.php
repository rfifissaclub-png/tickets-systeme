<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use App\Http\Resources\TicketAttachmentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_type' => new TicketTypeResource($this->whenLoaded('ticketType')),
            'client' => new UserResource($this->whenLoaded('client')),
            'agent' => new UserResource($this->whenLoaded('agent')),
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'first_response_at' => $this->first_response_at,
            'resolved_at' => $this->resolved_at,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'attachments' => TicketAttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
