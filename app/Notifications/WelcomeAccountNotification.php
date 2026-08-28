<?php

namespace App\Notifications;

use App\Notifications\Concerns\ProvidesEmailBranding;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeAccountNotification extends Notification
{
    use ProvidesEmailBranding;

    public function __construct(
        private readonly string $temporaryPassword,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $loginUrl = $frontendUrl.'/login';
        $appName = (string) config('app.name');

        return (new MailMessage)
            ->mailer('smtp')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('Welcome to '.$appName)
            ->action('Login to Rosewood Royale', $loginUrl)
            ->view([
                'html' => 'emails.auth.welcome-account',
                'text' => 'emails.auth.welcome-account-text',
            ], [
                'appName' => $appName,
                'userName' => (string) ($notifiable->name ?? ''),
                'loginEmail' => (string) ($notifiable->email ?? ''),
                'temporaryPassword' => $this->temporaryPassword,
                'logoSrc' => $this->brandLogoSrc(),
            ]);
    }
}
