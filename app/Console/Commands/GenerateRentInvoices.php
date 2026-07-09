<?php

namespace App\Console\Commands;

use App\Services\InvoiceService;
use Illuminate\Console\Command;

class GenerateRentInvoices extends Command
{
    protected $signature = 'invoices:generate-rent';

    protected $description = 'Generate monthly rent invoices for active installment contracts';

    public function handle(InvoiceService $invoiceService): int
    {
        $count = $invoiceService->generateRentInvoicesForToday();

        $this->info("Generated {$count} rent invoice(s).");

        return self::SUCCESS;
    }
}
