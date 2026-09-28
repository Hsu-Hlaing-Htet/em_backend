<?php

namespace App\Console\Commands;

use App\Services\InvoiceLateFeeService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ApplyInvoiceLateFeesCommand extends Command
{
    protected $signature = 'invoices:apply-late-fees
                            {--date= : Run as of this date (Y-m-d). Defaults to today.}';

    protected $description = 'Apply snapshotted Late Fee policies to unpaid invoices after due date + grace';

    public function handle(InvoiceLateFeeService $invoiceLateFeeService): int
    {
        $dateOption = $this->option('date');
        $asOf = $dateOption
            ? Carbon::parse((string) $dateOption)->startOfDay()
            : now()->startOfDay();

        $this->info('Applying late fees as of '.$asOf->toDateString().'…');

        $result = $invoiceLateFeeService->applyDueLateFees($asOf);

        $this->info(sprintf(
            'Late fee amounts updated: %d | Marked overdue: %d',
            $result['updated'],
            $result['overdue'],
        ));

        return self::SUCCESS;
    }
}
