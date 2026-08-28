<?php

namespace App\Console\Commands;

use App\Services\InvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateScheduledInvoicesCommand extends Command
{
    protected $signature = 'invoices:generate-scheduled
                            {--date= : Run as of this date (Y-m-d). Defaults to today.}';

    protected $description = 'Generate draft rent and sale-installment invoices 7 days before each due date';

    public function handle(InvoiceService $invoiceService): int
    {
        $dateOption = $this->option('date');
        $asOf = $dateOption
            ? Carbon::parse((string) $dateOption)->startOfDay()
            : now()->startOfDay();

        $this->info('Generating scheduled invoices as of '.$asOf->toDateString().'…');

        $result = $invoiceService->generateScheduledInvoices($asOf);

        $this->info(sprintf(
            'Created: %d | Skipped: %d | Errors: %d',
            $result['created'],
            $result['skipped'],
            $result['errors'],
        ));

        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
