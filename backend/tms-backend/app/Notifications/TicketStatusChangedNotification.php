<?php

namespace App\Notifications;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Notifications\Channels\TenantDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Ticket $ticket, private readonly TicketStatus $previousStatus) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return [TenantDatabaseChannel::class, 'mail', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toTenantDatabase(mixed $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'subject' => $this->ticket->subject,
            'from_status' => $this->previousStatus->value,
            'to_status' => $this->ticket->status->value,
            'message' => "Ticket \"{$this->ticket->subject}\" is now {$this->ticket->status->value}.",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return $this->toTenantDatabase($notifiable);
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Ticket status updated: {$this->ticket->subject}")
            ->line("Ticket \"{$this->ticket->subject}\" changed from {$this->previousStatus->value} to {$this->ticket->status->value}.")
            ->action('View ticket', rtrim(config('app.frontend_url'), '/')."/tickets/{$this->ticket->id}");
    }
}
