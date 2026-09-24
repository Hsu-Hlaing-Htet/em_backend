<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentApprovedNotification extends Notification
{
    public function __construct(
        private readonly Payment $payment,
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
        $appName = (string) config('app.name');

        return (new MailMessage)
            ->mailer('smtp')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('Payment Approved')
            ->view([
                'html' => 'emails.payments.payment-approved',
                'text' => 'emails.payments.payment-approved-text',
            ], [
                'appName' => $appName,
                'userName' => (string) ($notifiable->name ?? ''),
            ]);
    }
}
