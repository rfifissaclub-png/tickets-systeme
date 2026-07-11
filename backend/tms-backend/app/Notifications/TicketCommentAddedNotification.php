<?php

namespace App\Notifications;

use App\Models\TicketComment;
use App\Notifications\Channels\TenantDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCommentAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly TicketComment $comment) {}

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
        $ticket = $this->comment->ticket;

        return [
            'ticket_id' => $ticket->id,
            'comment_id' => $this->comment->id,
            'subject' => $ticket->subject,
            'author' => $this->comment->author->name,
            'message' => "{$this->comment->author->name} replied to \"{$ticket->subject}\".",
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
        $ticket = $this->comment->ticket;

        return (new MailMessage)
            ->subject("New reply on: {$ticket->subject}")
            ->line("{$this->comment->author->name} replied to a ticket you're following.")
            ->line($this->comment->body)
            ->action('View ticket', rtrim(config('app.frontend_url'), '/')."/tickets/{$ticket->id}");
    }
}
