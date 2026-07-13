<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail-only, deliberately: unlike Ticket*Notification, this carries a
 * plaintext password. Adding TenantDatabaseChannel/'broadcast' here would
 * persist that password in the notifications table indefinitely and push it
 * over the websocket — the mail channel delivers it once and doesn't retain
 * it server-side.
 */
class UserAccountCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $email,
        private readonly string $plainPassword,
        // Null only for a platform-level account (Super Admin) — the login
        // form's "workspace" field resolves a tenant by this slug, so any
        // tenant-scoped user (everyone else) cannot actually sign in without
        // it, and has no other way to learn it.
        private readonly ?string $tenantSlug = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('messages.users.welcome_email_subject'))
            ->line(__('messages.users.welcome_email_intro'));

        if ($this->tenantSlug !== null) {
            $message->line(__('messages.users.welcome_email_workspace', ['workspace' => $this->tenantSlug]));
        }

        return $message
            ->line(__('messages.users.welcome_email_login', ['email' => $this->email]))
            ->line(__('messages.users.welcome_email_password', ['password' => $this->plainPassword]))
            ->line(__('messages.users.welcome_email_change_password'))
            ->action(__('messages.users.welcome_email_action'), rtrim(config('app.frontend_url'), '/').'/login');
    }
}
