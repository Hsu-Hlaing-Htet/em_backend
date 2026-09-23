<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRejectedNotification extends Notification
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
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $actionUrl = $frontendUrl.'/customer/payments/'.$this->payment->id;
        $appName = (string) config('app.name');

        return (new MailMessage)
            ->mailer('smtp')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('Payment Rejected')
            ->view([
                'html' => 'emails.payments.payment-rejected',
                'text' => 'emails.payments.payment-rejected-text',
            ], [
                'appName' => $appName,
                'userName' => (string) ($notifiable->name ?? ''),
                'actionUrl' => $actionUrl,
                'displayableActionUrl' => $actionUrl,
                'actionLabel' => 'View Payment →',
            ]);
    }
}
