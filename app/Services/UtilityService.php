<?php

namespace App\Services;

use App\Exceptions\ConcurrentConflictException;
use App\Models\Building;
use App\Models\Room;
use App\Models\Utility;
use App\Models\UtilityItem;
use App\Models\UtilityRate;
use App\Models\UtilityType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class UtilityService
{
    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly InvoiceService $invoiceService,
        private readonly UtilityBillingPeriodService $billingPeriodService,
    ) {}

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Utility::query()
            ->with(['room.building', 'contract.user.profile', 'items.utilityType', 'creator'])
            ->latest('billing_month')
            ->latest('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['room_id'])) {
            $query->where('room_id', (int) $filters['room_id']);
        }

        if (! empty($filters['billing_month_from'])) {
            $query->whereDate('billing_month', '>=', Carbon::parse($filters['billing_month_from'])->toDateString());
        }

        if (! empty($filters['billing_month_to'])) {
            $query->whereDate('billing_month', '<=', Carbon::parse($filters['billing_month_to'])->toDateString());
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->whereHas('room', fn ($roomQuery) => $roomQuery
                    ->where('room_number', 'like', "%{$search}%")
                    ->orWhereHas('building', fn ($buildingQuery) => $buildingQuery
                        ->where('building_name', 'like', "%{$search}%")))
                    ->orWhereHas('contract.user', fn ($userQuery) => $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): Utility
    {
        return Utility::query()
            ->with(['room.building', 'contract.user.profile', 'items.utilityType', 'creator', 'approver'])
            ->findOrFail($id);
    }

    /**
     * @param  array<int>  $roomIds
     * @return array{unit_price: float, rooms: array<int, array{room_id: int, contract_id: int|null, previous_reading: float, has_previous_data: bool, next_billing_month: string|null}>}
     */
    public function formData(int $utilityTypeId, string $billingMonth, array $roomIds): array
    {
        $month = Carbon::parse($billingMonth)->startOfMonth();
        $unitPrice = $this->activeRateForType($utilityTypeId);

        $rooms = collect($roomIds)->map(function (int $roomId) use ($utilityTypeId, $month) {
            $contractId = null;

            try {
                $contract = $this->billingPeriodService->resolveContractForRoomMonth($roomId, $month);
                $contractId = $contract->id;
            } catch (ValidationException) {
                $contract = null;
            }

            $previous = $this->previousReadingData($roomId, $utilityTypeId, $month, $contractId);

            return [
                'room_id' => $roomId,
                'contract_id' => $contractId,
                'previous_reading' => $previous['previous_reading'],
                'has_previous_data' => $previous['has_previous_data'],
                'next_billing_month' => $contract
                    ? $this->nextExpectedBillingMonth($contract)?->toDateString()
                    : null,
            ];
        })->values()->all();

        return [
            'unit_price' => $unitPrice,
            'rooms' => $rooms,
        ];
    }

    /**
     * @return array{previous_reading: float, has_previous_data: bool}
     */
    public function previousReadingData(
        int $roomId,
        int $utilityTypeId,
        Carbon $billingMonth,
        ?int $contractId = null,
    ): array {
        $previousMonth = $billingMonth->copy()->subMonth()->startOfMonth();

        $query = Utility::query()
            ->where('room_id', $roomId)
            ->whereDate('billing_month', $previousMonth)
            ->whereHas('items', fn ($itemQuery) => $itemQuery->where('utility_type_id', $utilityTypeId))
            ->with(['items' => fn ($itemQuery) => $itemQuery->where('utility_type_id', $utilityTypeId)]);

        if ($contractId) {
            $query->where('contract_id', $contractId);
        }

        $utility = $query->first();

        if (! $utility || $utility->items->isEmpty()) {
            return [
                'previous_reading' => 0.0,
                'has_previous_data' => false,
            ];
        }

        return [
            'previous_reading' => (float) $utility->items->first()->current_reading,
            'has_previous_data' => true,
        ];
    }

    public function activeRateForType(int $utilityTypeId): float
    {
        $rate = UtilityRate::query()
            ->where('utility_type_id', $utilityTypeId)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (! $rate) {
            throw new InvalidArgumentException('No active utility rate found for the selected utility type.');
        }

        return (float) $rate->unit_price;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, summary: array{total_rows: int, valid_rows: int, invalid_rows: int}}
     */
    public function previewBulkImport(int $utilityTypeId, array $rows): array
    {
        $buildings = Building::query()->get()->keyBy(fn (Building $building) => $this->normalizeImportValue($building->building_name));
        $utilityType = UtilityType::query()->find($utilityTypeId);
        $rooms = Room::query()
            ->with('building')
            ->get()
            ->keyBy(fn (Room $room) => $this->roomImportKey($room->building_id, $room->room_number));
        $roomsByNumber = Room::query()
            ->with('building')
            ->get()
            ->groupBy(fn (Room $room) => $this->normalizeImportValue($room->room_number));
        $seen = [];
        $lastValidBillingMonths = [];
        $lastValidReadings = [];
        $importRows = collect($rows)->values()->map(function (array $row, int $index) {
            try {
                $sortMonth = $this->parseImportBillingMonth($row['billing_month'] ?? '')->toDateString();
            } catch (InvalidArgumentException) {
                $sortMonth = '9999-12-31';
            }

            return [
                'row' => $row,
                'index' => $index,
                'sort_building' => $this->normalizeImportValue($row['building'] ?? ''),
                'sort_room' => $this->normalizeImportValue($row['room_number'] ?? ''),
                'sort_month' => $sortMonth,
            ];
        })->sortBy([
            ['sort_building', 'asc'],
            ['sort_room', 'asc'],
            ['sort_month', 'asc'],
            ['index', 'asc'],
        ])->values();

        $previewRows = $importRows->map(function (array $importRow) use (
            $buildings,
            $utilityType,
            $rooms,
            $roomsByNumber,
            &$seen,
            &$lastValidBillingMonths,
            &$lastValidReadings,
        ) {
            $row = $importRow['row'];
            $index = $importRow['index'];
            $messages = [];
            $building = null;
            $room = null;
            $contract = null;
            $billingMonth = null;
            $readingDate = null;
            $previousReading = null;
            $currentReading = null;

            $buildingName = trim((string) ($row['building'] ?? ''));
            $roomNumber = trim((string) ($row['room_number'] ?? ''));
            $billingMonthValue = $row['billing_month'] ?? '';
            $readingDateValue = $row['reading_date'] ?? '';

            if ($buildingName === '') {
                $messages[] = 'Building is required.';
            } else {
                $building = $buildings->get($this->normalizeImportValue($buildingName));
                if (! $building) {
                    $messages[] = 'Building not found.';
                }
            }

            if ($roomNumber === '') {
                $messages[] = 'Room Number is required.';
            } elseif ($building) {
                $room = $rooms->get($this->roomImportKey($building->id, $roomNumber));
                if (! $room) {
                    $roomNumberMatches = $roomsByNumber->get($this->normalizeImportValue($roomNumber), collect());
                    $messages[] = $roomNumberMatches->isNotEmpty()
                        ? 'Room does not belong to building.'
                        : 'Room not found.';
                }
            } elseif ($roomsByNumber->get($this->normalizeImportValue($roomNumber), collect())->isEmpty()) {
                $messages[] = 'Room not found.';
            }

            if (! $utilityType) {
                $messages[] = 'Utility type not found.';
            }

            try {
                $billingMonth = $this->parseImportBillingMonth($billingMonthValue);
            } catch (InvalidArgumentException) {
                $messages[] = 'Invalid billing month. Use DD/MM/YYYY format.';
            }

            try {
                $readingDate = $this->parseImportDate($readingDateValue, 'd/m/Y');
            } catch (InvalidArgumentException) {
                $messages[] = 'Invalid reading date. Use DD/MM/YYYY format.';
            }

            if ($billingMonth && $readingDate && ! $readingDate->isSameMonth($billingMonth)) {
                $messages[] = 'Invalid reading date. Reading Date must fall within the specified Billing Month.';
            }

            if (! $this->isImportNumber($row['current_reading'] ?? null)) {
                $messages[] = 'Current Reading must be a number.';
            } else {
                $currentReading = (float) $row['current_reading'];
            }

            if ($room && $utilityType) {
                $meterKey = $this->bulkImportMeterKey($room->id, $utilityType->id);
                $previousReading = $lastValidReadings[$meterKey]
                    ?? $this->latestMeterReadingForRoomType($room->id, $utilityType->id);
            }

            if ($previousReading !== null && $currentReading !== null && $currentReading < $previousReading) {
                $messages[] = 'Current reading cannot be less than previous reading.';
            }

            if ($room && $utilityType && $billingMonth) {
                $importKey = implode('|', [
                    $room->id,
                    $utilityType->id,
                    $billingMonth->toDateString(),
                ]);

                if (isset($seen[$importKey])) {
                    $messages[] = sprintf('Duplicate row in this file. Already listed on row %d.', $seen[$importKey]);
                } else {
                    $seen[$importKey] = $index + 1;
                }

                try {
                    $contract = $this->billingPeriodService->resolveContractForRoomMonth($room->id, $billingMonth);
                    $contract->loadMissing('user.profile');
                    $existingForRoomMonth = Utility::query()
                        ->where('room_id', $room->id)
                        ->whereDate('billing_month', $billingMonth->toDateString())
                        ->first();

                    if ($existingForRoomMonth) {
                        if ($existingForRoomMonth->items()->where('utility_type_id', $utilityType->id)->exists()) {
                            $messages[] = sprintf(
                                'Duplicate utility record: %s already exists for %s.',
                                $utilityType->name,
                                $billingMonth->format('F Y'),
                            );
                        }

                        if (! in_array($existingForRoomMonth->status, ['draft', 'pending'], true)) {
                            $messages[] = 'Only draft or pending utility bills can accept additional readings.';
                        }
                    } elseif ($readingDate) {
                        $sequenceKey = $this->bulkImportSequenceKey($contract->id, $utilityType->id);

                        if (isset($lastValidBillingMonths[$sequenceKey])) {
                            $this->billingPeriodService->assertCanCreateAfterBillingMonth(
                                $contract,
                                $billingMonth,
                                $lastValidBillingMonths[$sequenceKey],
                                $readingDate,
                            );
                        } else {
                            $this->billingPeriodService->assertCanCreate($contract, $billingMonth, $readingDate);
                        }
                    }
                } catch (ValidationException $exception) {
                    foreach ($exception->errors() as $errors) {
                        foreach ($errors as $error) {
                            $messages[] = str_contains(strtolower($error), 'no active contract')
                                ? 'No active contract/customer found.'
                                : $error;
                        }
                    }
                } catch (InvalidArgumentException $exception) {
                    $messages[] = $exception->getMessage();
                }
            }

            $isValid = count($messages) === 0;

            if ($isValid && $room && $utilityType && $contract && $billingMonth && $currentReading !== null) {
                $lastValidBillingMonths[$this->bulkImportSequenceKey($contract->id, $utilityType->id)] = $billingMonth->copy()->startOfMonth();
                $lastValidReadings[$this->bulkImportMeterKey($room->id, $utilityType->id)] = $currentReading;
            }

            return [
                ...$row,
                'billing_month' => $billingMonth?->format('d/m/Y') ?? $row['billing_month'] ?? '',
                'reading_date' => $readingDate?->format('d/m/Y') ?? $row['reading_date'] ?? '',
                'row_number' => $index + 1,
                'status' => $isValid ? 'Valid' : 'Invalid',
                'is_valid' => $isValid,
                'messages' => array_values(array_unique($messages)),
                'utility_type' => $utilityType?->name,
                'previous_reading' => $previousReading,
                'building_id' => $building?->id,
                'room_id' => $room?->id,
                'utility_type_id' => $utilityType?->id,
                'contract_id' => $contract?->id,
                'contract_number' => $contract?->contract_number,
                'customer_name' => $contract?->user?->name,
                'customer_email' => $contract?->user?->email,
            ];
        })->sortBy('row_number')->values()->all();

        $validRows = collect($previewRows)->where('is_valid', true)->count();

        return [
            'rows' => $previewRows,
            'summary' => [
                'total_rows' => count($previewRows),
                'valid_rows' => $validRows,
                'invalid_rows' => count($previewRows) - $validRows,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, Utility>
     */
    public function confirmBulkImport(int $utilityTypeId, array $rows): array
    {
        $preview = $this->previewBulkImport($utilityTypeId, $rows);
        $validRows = collect($preview['rows'])->where('is_valid', true)->values();
        $invalidRows = collect($preview['rows'])->where('is_valid', false);

        if ($validRows->isEmpty() || $invalidRows->isNotEmpty()) {
            throw ValidationException::withMessages([
                'rows' => [$validRows->isEmpty()
                    ? 'No valid utility rows available to import.'
                    : 'Please fix all invalid utility rows before importing.'],
            ]);
        }

        return DB::transaction(function () use ($validRows) {
            $created = [];

            foreach ($this->sortBulkImportRowsForCreation($validRows) as $row) {
                $billingMonth = $this->parseImportBillingMonth($row['billing_month'])
                    ->startOfMonth()
                    ->toDateString();
                $readingDate = $this->parseImportDate($row['reading_date'], 'd/m/Y')
                    ->toDateString();

                $batch = $this->createBatch([
                    'utility_type_id' => (int) $row['utility_type_id'],
                    'billing_month' => $billingMonth,
                    'reading_date' => $readingDate,
                    'entries' => [[
                        'room_id' => (int) $row['room_id'],
                        'previous_reading' => (float) $row['previous_reading'],
                        'current_reading' => (float) $row['current_reading'],
                    ]],
                ]);

                array_push($created, ...$batch);
            }

            return $created;
        });
    }

    public function previousReading(int $roomId, int $utilityTypeId, Carbon $billingMonth, ?int $contractId = null): float
    {
        return $this->previousReadingData($roomId, $utilityTypeId, $billingMonth, $contractId)['previous_reading'];
    }

    private function latestMeterReadingForRoomType(int $roomId, int $utilityTypeId): float
    {
        $utility = Utility::query()
            ->where('room_id', $roomId)
            ->whereHas('items', fn ($itemQuery) => $itemQuery->where('utility_type_id', $utilityTypeId))
            ->with(['items' => fn ($itemQuery) => $itemQuery->where('utility_type_id', $utilityTypeId)])
            ->latest('billing_month')
            ->latest('id')
            ->first();

        if (! $utility || $utility->items->isEmpty()) {
            return 0.0;
        }

        return (float) $utility->items->first()->current_reading;
    }

    private function bulkImportMeterKey(int $roomId, int $utilityTypeId): string
    {
        return $roomId.'|'.$utilityTypeId;
    }

    private function bulkImportSequenceKey(int $contractId, int $utilityTypeId): string
    {
        return $contractId.'|'.$utilityTypeId;
    }

    /**
     * @param  iterable<array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function sortBulkImportRowsForCreation(iterable $rows): array
    {
        return collect($rows)->sortBy([
            ['room_id', 'asc'],
            ['utility_type_id', 'asc'],
            ['billing_month', 'asc'],
            ['row_number', 'asc'],
        ])->values()->all();
    }

    private function normalizeImportValue(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function roomImportKey(int $buildingId, ?string $roomNumber): string
    {
        return $buildingId.'|'.$this->normalizeImportValue($roomNumber);
    }

    private function parseImportDate(mixed $value, string $format): Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        if (is_numeric($value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->startOfDay();
        }

        $value = trim((string) $value);

        if ($value === '') {
            throw new InvalidArgumentException('Date is required.');
        }

        $date = Carbon::createFromFormat('!'.$format, $value);

        if (! $date || $date->format($format) !== $value) {
            throw new InvalidArgumentException('Invalid date format.');
        }

        return $date->startOfDay();
    }

    private function parseImportBillingMonth(mixed $value): Carbon
    {
        if ($value instanceof \DateTimeInterface || is_numeric($value)) {
            return $this->parseImportDate($value, 'd/m/Y');
        }

        $value = trim((string) $value);

        foreach (['d/m/Y', 'j/n/Y', 'd/m/y', 'j/n/y'] as $format) {
            $date = Carbon::createFromFormat('!'.$format, $value);

            if ($date && $date->format($format) === $value) {
                return $date->startOfDay();
            }
        }

        throw new InvalidArgumentException('Invalid billing month.');
    }

    private function isImportNumber(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return is_numeric($value);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, Utility>
     */
    public function createBatch(array $data): array
    {
        $billingMonth = Carbon::parse($data['billing_month'])->startOfMonth();
        $readingDate = array_key_exists('reading_date', $data) && $data['reading_date']
            ? Carbon::parse($data['reading_date'])->startOfDay()
            : $this->billingPeriodService->defaultReadingDate($billingMonth);
        $utilityTypeId = (int) $data['utility_type_id'];
        $defaultUnitPrice = $this->activeRateForType($utilityTypeId);

        return DB::transaction(function () use ($data, $billingMonth, $readingDate, $utilityTypeId, $defaultUnitPrice) {
            $created = [];

            foreach ($data['entries'] as $entry) {
                $roomId = (int) $entry['room_id'];
                $contract = $this->billingPeriodService->resolveContractForRoomMonth($roomId, $billingMonth);

                $existing = Utility::query()
                    ->where('contract_id', $contract->id)
                    ->whereDate('billing_month', $billingMonth->toDateString())
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ($existing->items()->where('utility_type_id', $utilityTypeId)->exists()) {
                        throw ValidationException::withMessages([
                            'billing_month' => [
                                sprintf(
                                    'A utility record already exists for %s on this contract.',
                                    $billingMonth->format('F Y'),
                                ),
                            ],
                        ]);
                    }

                    if (! in_array($existing->status, ['draft', 'pending'], true)) {
                        throw new InvalidArgumentException('Only draft or pending utility bills can accept additional readings.');
                    }
                } else {
                    $this->billingPeriodService->assertCanCreate($contract, $billingMonth, $readingDate);
                }

                $previousReading = array_key_exists('previous_reading', $entry) && $entry['previous_reading'] !== null
                    ? (float) $entry['previous_reading']
                    : $this->previousReading($roomId, $utilityTypeId, $billingMonth, $contract->id);
                $currentReading = (float) $entry['current_reading'];
                $unitPrice = array_key_exists('unit_price', $entry) && $entry['unit_price'] !== null
                    ? (float) $entry['unit_price']
                    : $defaultUnitPrice;

                if ($currentReading < $previousReading) {
                    throw new InvalidArgumentException('Current reading cannot be less than previous reading.');
                }

                $usage = $currentReading - $previousReading;
                $amount = round($usage * $unitPrice, 2);

                $utility = $existing ?? Utility::query()->create([
                    'room_id' => $roomId,
                    'contract_id' => $contract->id,
                    'billing_month' => $billingMonth->toDateString(),
                    'reading_date' => $readingDate->toDateString(),
                    'status' => 'pending',
                    'total_amount' => 0,
                    'created_by' => Auth::id(),
                ]);

                UtilityItem::query()->create([
                    'utility_id' => $utility->id,
                    'utility_type_id' => $utilityTypeId,
                    'previous_reading' => $previousReading,
                    'current_reading' => $currentReading,
                    'usage' => $usage,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                ]);

                $utility->update([
                    'total_amount' => round((float) $utility->items()->sum('amount'), 2),
                ]);

                $created[] = $utility->fresh(['room.building', 'contract.user.profile', 'items.utilityType', 'creator']);
            }

            return $created;
        });
    }

    public function create(array $data): Utility
    {
        return DB::transaction(function () use ($data) {
            $billingMonth = Carbon::parse($data['billing_month'])->startOfMonth();
            $readingDate = array_key_exists('reading_date', $data) && $data['reading_date']
                ? Carbon::parse($data['reading_date'])->startOfDay()
                : $this->billingPeriodService->defaultReadingDate($billingMonth);
            $roomId = (int) $data['room_id'];
            $contract = ! empty($data['contract_id'])
                ? \App\Models\Contract::query()->findOrFail((int) $data['contract_id'])
                : $this->billingPeriodService->resolveContractForRoomMonth($roomId, $billingMonth);

            if ((int) $contract->room_id !== $roomId) {
                throw ValidationException::withMessages([
                    'room_id' => ['Selected room does not match the contract room.'],
                ]);
            }

            $this->billingPeriodService->assertCanCreate($contract, $billingMonth, $readingDate);

            $utility = Utility::query()->create([
                'room_id' => $roomId,
                'contract_id' => $contract->id,
                'billing_month' => $billingMonth->toDateString(),
                'reading_date' => $readingDate->toDateString(),
                'status' => 'draft',
                'total_amount' => 0,
                'created_by' => Auth::id(),
            ]);

            $total = $this->syncItems($utility, $data['utility_items'] ?? $data['items'] ?? []);
            $utility->update(['total_amount' => $total]);

            return $utility->fresh(['room.building', 'contract.user.profile', 'items.utilityType', 'creator']);
        });
    }

    public function update(Utility $utility, array $data): Utility
    {
        if (! in_array($utility->status, ['draft'], true)) {
            throw new InvalidArgumentException('Only draft utility bills can be edited.');
        }

        return DB::transaction(function () use ($utility, $data) {
            $billingMonth = Carbon::parse($data['billing_month'] ?? $utility->billing_month)->startOfMonth();
            $readingDate = array_key_exists('reading_date', $data) && $data['reading_date']
                ? Carbon::parse($data['reading_date'])->startOfDay()
                : ($utility->reading_date
                    ? Carbon::parse($utility->reading_date)->startOfDay()
                    : $this->billingPeriodService->defaultReadingDate($billingMonth));
            $roomId = (int) ($data['room_id'] ?? $utility->room_id);
            $contract = ! empty($data['contract_id'])
                ? \App\Models\Contract::query()->findOrFail((int) $data['contract_id'])
                : ($utility->contract_id
                    ? $utility->contract()->firstOrFail()
                    : $this->billingPeriodService->resolveContractForRoomMonth($roomId, $billingMonth));

            if ((int) $contract->room_id !== $roomId) {
                throw ValidationException::withMessages([
                    'room_id' => ['Selected room does not match the contract room.'],
                ]);
            }

            $this->billingPeriodService->assertCanCreate(
                $contract,
                $billingMonth,
                $readingDate,
                $utility->id,
                Carbon::parse($utility->billing_month)->startOfMonth(),
            );

            $utility->update([
                'room_id' => $roomId,
                'contract_id' => $contract->id,
                'billing_month' => $billingMonth->toDateString(),
                'reading_date' => $readingDate->toDateString(),
            ]);

            if (array_key_exists('utility_items', $data) || array_key_exists('items', $data)) {
                $total = $this->syncItems($utility, $data['utility_items'] ?? $data['items'] ?? []);
                $utility->update(['total_amount' => $total]);
            }

            return $utility->fresh(['room.building', 'contract.user.profile', 'items.utilityType', 'creator']);
        });
    }

    public function submit(Utility $utility): Utility
    {
        return DB::transaction(function () use ($utility): Utility {
            /** @var Utility $locked */
            $locked = Utility::query()
                ->whereKey($utility->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'draft') {
                throw new ConcurrentConflictException('Only draft utility bills can be submitted.');
            }

            if ($locked->items()->count() === 0) {
                throw new InvalidArgumentException('Add at least one utility item before submitting.');
            }

            $locked->update(['status' => 'pending']);

            return $locked->fresh(['room.building', 'contract.user.profile', 'items.utilityType', 'creator']);
        });
    }

    public function approve(Utility $utility): Utility
    {
        return DB::transaction(function () use ($utility): Utility {
            /** @var Utility $locked */
            $locked = Utility::query()
                ->whereKey($utility->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending') {
                throw new ConcurrentConflictException('Only pending utility bills can be approved.');
            }

            $locked = $this->approvalService->approve($locked, ['pending']);
            $this->invoiceService->generateFromUtility($locked);

            return $locked->fresh(['room.building', 'contract.user.profile', 'items.utilityType', 'creator', 'approver']);
        });
    }

    public function reject(Utility $utility, ?string $reason = null): Utility
    {
        return DB::transaction(function () use ($utility, $reason): Utility {
            /** @var Utility $locked */
            $locked = Utility::query()
                ->whereKey($utility->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending') {
                throw new ConcurrentConflictException('Only pending utility bills can be rejected.');
            }

            return $this->approvalService->reject($locked, $reason, ['pending'])
                ->fresh(['room.building', 'contract.user.profile', 'items.utilityType', 'creator', 'approver']);
        });
    }

    public function delete(Utility $utility): void
    {
        if ($utility->status !== 'draft') {
            throw new InvalidArgumentException('Only draft utility bills can be deleted.');
        }

        $utility->delete();
    }

    private function nextExpectedBillingMonth(\App\Models\Contract $contract): ?Carbon
    {
        $latest = Utility::query()
            ->where('contract_id', $contract->id)
            ->orderByDesc('billing_month')
            ->orderByDesc('id')
            ->first();

        if (! $latest) {
            return Carbon::parse($contract->start_date)->startOfMonth();
        }

        $next = Carbon::parse($latest->billing_month)->startOfMonth()->addMonth();

        if ($contract->end_date && $next->gt(Carbon::parse($contract->end_date)->startOfMonth())) {
            return null;
        }

        return $next;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Utility $utility, array $items): float
    {
        $utility->items()->delete();

        $total = 0.0;

        foreach ($items as $item) {
            $previous = (float) ($item['previous_reading'] ?? 0);
            $current = (float) ($item['current_reading'] ?? 0);

            if ($current < $previous) {
                throw new InvalidArgumentException('Current reading cannot be less than previous reading.');
            }

            $usage = $current - $previous;
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $amount = round($usage * $unitPrice, 2);

            UtilityItem::query()->create([
                'utility_id' => $utility->id,
                'utility_type_id' => $item['utility_type_id'],
                'previous_reading' => $previous,
                'current_reading' => $current,
                'usage' => $usage,
                'unit_price' => $unitPrice,
                'amount' => $amount,
            ]);

            $total += $amount;
        }

        return round($total, 2);
    }
}
