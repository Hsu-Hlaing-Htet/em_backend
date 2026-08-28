<?php

namespace App\Notifications;

use App\Notifications\Concerns\ProvidesEmailBranding;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use ProvidesEmailBranding;

    public function __construct(
        public string $token,
        public string $recipientEmail,
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
        $resetUrl = $frontendUrl.'/reset-password?'.http_build_query(
            [
                'token' => $this->token,
                'email' => $this->recipientEmail,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

        $expireMinutes = (int) config('auth.passwords.users.expire');

        return (new MailMessage)
            ->mailer('smtp')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('Reset Your Password - '.config('app.name'))
            ->action('Reset Password', $resetUrl)
            ->view([
                'html' => 'emails.auth.reset-password',
                'text' => 'emails.auth.reset-password-text',
            ], [
                'appName' => config('app.name'),
                'expireMinutes' => $expireMinutes,
                'logoSrc' => $this->brandLogoSrc(),
            ]);
    }
}
