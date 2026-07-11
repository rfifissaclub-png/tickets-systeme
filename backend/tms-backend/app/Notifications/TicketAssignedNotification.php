<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Notifications\Channels\TenantDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Ticket $ticket) {}

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
            'message' => "You've been assigned to ticket \"{$this->ticket->subject}\".",
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
            ->subject("Ticket assigned: {$this->ticket->subject}")
            ->line("You've been assigned to a ticket.")
            ->line("Subject: {$this->ticket->subject}")
            ->action('View ticket', rtrim(config('app.frontend_url'), '/')."/tickets/{$this->ticket->id}");
    }
}
