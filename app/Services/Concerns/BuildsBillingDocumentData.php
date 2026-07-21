<?php

namespace App\Services\Concerns;

use App\Models\User;

trait BuildsBillingDocumentData
{
    protected function footerAddress(): string
    {
        return 'No. 12, Kabar Aye Pagoda Road, Bahan Township, Yangon';
    }

    protected function customerName(?User $user): string
    {
        return $user?->name ?? '-';
    }

    protected function formatCurrency(float $amount): string
    {
        return number_format($amount, 0).' MMK';
    }

    protected function statusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'issued' => 'Issued',
            'partial' => 'Partial',
            'paid' => 'Paid',
            'overdue' => 'Overdue',
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => $status ?? '-',
        };
    }
}
