<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Database\Seeders\Support\SeedAssetImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * Rematerialize demonstration payment proof images onto the public disk.
 *
 * Source: database/seeders/assets/payments/*
 * Destination: storage/app/public/payments/...
 *
 * Skips authentic customer/admin uploads stored under payment-proofs/.
 * Does not change amounts, invoice status, payment approval, or receipts.
 */
class SyncSeedPaymentProofsCommand extends Command
{
    protected $signature = 'rosewood:sync-seed-payment-proofs';

    protected $description = 'Copy method-matching seed payment proof images onto the public storage disk';

    public function handle(): int
    {
        Artisan::call('storage:link', ['--force' => true]);
        $this->line(trim(Artisan::output()) ?: 'Storage link ensured.');

        SeedAssetImage::assertPaymentAssetLibraryPresent();

        $copied = 0;
        $cleared = 0;
        $preserved = 0;

        Payment::query()
            ->with('paymentMethod')
            ->orderBy('id')
            ->chunkById(100, function ($payments) use (&$copied, &$cleared, &$preserved): void {
                foreach ($payments as $payment) {
                    if (SeedAssetImage::isUploadedPaymentProofPath($payment->proof_image_path)) {
                        $preserved++;

                        continue;
                    }

                    $path = SeedAssetImage::materializeProofForPayment($payment);

                    if ($path === $payment->proof_image_path) {
                        if ($path !== null) {
                            $copied++;
                        }

                        continue;
                    }

                    $payment->update(['proof_image_path' => $path]);

                    if ($path === null) {
                        $cleared++;
                    } else {
                        $copied++;
                    }
                }
            });

        $this->info("Seed payment proofs rematerialized: {$copied}");
        $this->info("Proof-less methods cleared: {$cleared}");
        $this->info("Uploaded proofs preserved: {$preserved}");
        $this->info('Public disk payments dir: '.(Storage::disk('public')->exists('payments') ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
