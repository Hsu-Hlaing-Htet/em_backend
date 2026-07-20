<?php

namespace Database\Seeders;

use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Role;
use App\Models\User;
use App\Models\Utility;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))
            ->first();

        if (! $admin) {
            $this->command?->warn('Admin user is required. Run UserSeeder first.');

            return;
        }

        $chargeTypes = ChargeType::query()->where('status', 'active')->get()->keyBy('slug');

        $approvedContracts = Contract::query()
            ->where(function ($query): void {
                $query->where(function ($nested): void {
                    $nested->where('type', 'rent')->where('status', 'active');
                })->orWhere(function ($nested): void {
                    $nested->where('type', 'sale')->where('status', 'approved');
                });
            })
            ->with('room')
            ->orderBy('id')
            ->get();

        $approvedUtilities = Utility::query()
            ->where('status', 'approved')
            ->orderBy('id')
            ->get();

        if ($approvedContracts->isEmpty()) {
            $this->command?->warn('Approved contracts are required. Run ContractSeeder first.');

            return;
        }

        /** @var list<array{status: string, reviewed: bool}> $invoiceStates */
        $invoiceStates = array_merge(
            array_map(fn () => ['status' => 'draft', 'reviewed' => false], range(1, 4)),
            array_map(fn () => ['status' => 'draft', 'reviewed' => true], range(1, 4)),
            array_map(fn () => ['status' => 'issued', 'reviewed' => true], range(1, 4)),
            array_map(fn () => ['status' => 'partial', 'reviewed' => true], range(1, 4)),
            array_map(fn () => ['status' => 'paid', 'reviewed' => true], range(1, 5)),
            array_map(fn () => ['status' => 'overdue', 'reviewed' => true], range(1, 4)),
        );

        shuffle($invoiceStates);

        $invoiceSequence = 1;
        $created = 0;
        $sources = $this->buildInvoiceSources($approvedContracts, $approvedUtilities);

        foreach ($sources->take(25) as $source) {
            $state = $invoiceStates[$created] ?? ['status' => 'draft', 'reviewed' => false];
            $status = $state['status'];
            $issuedDate = in_array($status, ['issued', 'partial', 'paid', 'overdue'], true)
                ? now()->subDays(fake()->numberBetween(3, 45))->toDateString()
                : null;

            $this->createInvoice(
                $admin,
                $source,
                $chargeTypes,
                'INV-'.now()->format('Ymd').'-'.str_pad((string) $invoiceSequence++, 4, '0', STR_PAD_LEFT),
                $status,
                $state['reviewed'],
                $issuedDate,
                now()->addDays(fake()->numberBetween(7, 30))->toDateString(),
            );

            $created++;
        }
    }

    /**
     * @return Collection<int, array{contract: Contract, utility: Utility|null, kind: string}>
     */
    private function buildInvoiceSources(Collection $contracts, Collection $utilities): Collection
    {
        $sources = collect();

        foreach ($contracts->where('type', 'rent') as $contract) {
            $sources->push([
                'contract' => $contract,
                'utility' => null,
                'kind' => 'rent',
            ]);
        }

        foreach ($contracts->where('type', 'sale') as $contract) {
            $sources->push([
                'contract' => $contract,
                'utility' => null,
                'kind' => 'sale',
            ]);
        }

        foreach ($utilities as $utility) {
            $contract = $contracts
                ->where('type', 'rent')
                ->firstWhere('room_id', $utility->room_id);

            if (! $contract) {
                continue;
            }

            $sources->push([
                'contract' => $contract,
                'utility' => $utility,
                'kind' => 'utility',
            ]);
        }

        return $sources->shuffle()->values()->pipe(function (Collection $items) {
            if ($items->count() >= 25) {
                return $items;
            }

            $expanded = $items->values();

            while ($expanded->count() < 25) {
                foreach ($items as $source) {
                    $expanded->push($source);
                    if ($expanded->count() >= 25) {
                        break;
                    }
                }
            }

            return $expanded->take(25)->values();
        });
    }

    /**
     * @param  Collection<string, ChargeType>  $chargeTypes
     * @param  array{contract: Contract, utility: Utility|null, kind: string}  $source
     */
    private function createInvoice(
        User $admin,
        array $source,
        Collection $chargeTypes,
        string $invoiceNumber,
        string $status,
        bool $reviewed,
        ?string $issuedDate,
        string $dueDate,
    ): Invoice {
        $contract = $source['contract'];
        $utility = $source['utility'];
        $items = match ($source['kind']) {
            'utility' => [[
                'charge_type_id' => $chargeTypes->get('utility-charges')?->id,
                'description' => 'Utility bill for '.$utility->billing_month->format('F Y'),
                'amount' => (float) $utility->total_amount,
            ]],
            'sale' => [[
                'charge_type_id' => $chargeTypes->get('booking-deposit')?->id,
                'description' => 'Booking deposit — '.$contract->contract_number,
                'amount' => (float) $contract->deposit_amount,
            ], [
                'charge_type_id' => $chargeTypes->get('sale-installment')?->id,
                'description' => 'Sale balance — '.$contract->contract_number,
                'amount' => max((float) $contract->contract_total - (float) $contract->deposit_amount, 0),
            ]],
            default => [[
                'charge_type_id' => $chargeTypes->get('monthly-rent')?->id,
                'description' => 'Monthly rent — '.now()->format('F Y'),
                'amount' => (float) $contract->room->rent_price,
            ]],
        };

        $totalAmount = collect($items)->sum('amount');
        $isIssued = in_array($status, ['issued', 'partial', 'paid', 'overdue'], true);

        $invoice = Invoice::query()->create([
            'contract_id' => $contract->id,
            'utility_id' => $utility?->id,
            'created_by' => $admin->id,
            'approved_by' => ($reviewed || $isIssued) ? $admin->id : null,
            'approved_at' => ($reviewed || $isIssued) ? now()->subDay() : null,
            'invoice_number' => $invoiceNumber,
            'type' => $source['kind'] === 'sale' ? 'other' : $source['kind'],
            'issued_date' => $issuedDate,
            'due_date' => $dueDate,
            'late_fee' => $status === 'overdue' ? 10000 : 0,
            'total_amount' => $totalAmount,
            'status' => $status,
        ]);

        foreach ($items as $item) {
            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                ...$item,
            ]);
        }

        return $invoice;
    }
}
