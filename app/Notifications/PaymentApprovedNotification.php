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
        $this->payment->loadMissing(['receipt', 'invoice.contract.user']);

        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $receiptId = $this->payment->receipt?->id;
        $actionUrl = $receiptId
            ? $frontendUrl.'/customer/receipts/'.$receiptId
            : $frontendUrl.'/customer/receipts';
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
                'actionUrl' => $actionUrl,
                'displayableActionUrl' => $actionUrl,
                'actionLabel' => 'View Receipt →',
            ]);
    }
}
