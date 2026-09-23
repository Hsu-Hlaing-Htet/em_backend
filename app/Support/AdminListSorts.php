<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Shared allowlisted sort maps for Admin list endpoints.
 *
 * Keys are UI/DataTable field names; values are DB columns or safe callbacks.
 */
final class AdminListSorts
{
    /**
     * Order by an explicit CASE rank (business order), not alphabetical labels.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, int>  $ranks  lowercase value => rank
     */
    public static function orderByCaseRank(
        Builder $query,
        string $direction,
        string $column,
        array $ranks,
    ): void {
        $cases = [];
        $bindings = [];

        foreach ($ranks as $value => $rank) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $value;
            $bindings[] = $rank;
        }

        $sql = 'CASE LOWER(COALESCE('.$column.", '')) ".implode(' ', $cases).' ELSE 0 END';

        $query->orderByRaw($sql.' '.$direction, $bindings);
    }

    /**
     * @return array<string, string|Closure>
     */
    public static function buildings(): array
    {
        return [
            'building_name' => 'building_name',
            'location' => 'location',
            'status' => 'status',
            'created_at' => 'created_at',
        ];
    }

    /**
     * @return array<string, string|Closure>
     */
    public static function rooms(): array
    {
        return [
            'room_number' => 'room_number',
            'floor_number' => 'floor_number',
            'area_sqft' => 'area_sqft',
            'type' => 'type',
            'status' => 'status',
            'created_at' => 'created_at',
            // Matches list display: rent→rent_price, sale→sale_price, both→sale_price (first shown value).
            'list_price' => static function (Builder $query, string $direction): void {
                $query->orderByRaw(
                    "CASE LOWER(COALESCE(rooms.type, ''))
                        WHEN 'rent' THEN rooms.rent_price
                        WHEN 'sale' THEN rooms.sale_price
                        WHEN 'both' THEN rooms.sale_price
                        ELSE COALESCE(rooms.sale_price, rooms.rent_price, 0)
                    END {$direction}"
                );
            },
            'building_name' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('buildings')
                        ->select('buildings.building_name')
                        ->whereColumn('buildings.id', 'rooms.building_id')
                        ->limit(1),
                    $direction
                );
            },
        ];
    }

    /**
     * @return array<string, string|Closure>
     */
    public static function accounts(): array
    {
        return [
            'name' => 'users.name',
            'email' => 'users.email',
            'status' => 'users.status',
            'created_at' => 'users.created_at',
        ];
    }

    /**
     * @return array<string, string|Closure>
     */
    public static function utilities(): array
    {
        return [
            'billing_month' => 'billing_month',
            'total_amount' => 'total_amount',
            'status' => static function (Builder $query, string $direction): void {
                self::orderByCaseRank($query, $direction, 'utilities.status', [
                    'draft' => 1,
                    'pending' => 2,
                    'approved' => 3,
                    'rejected' => 4,
                ]);
            },
            'created_at' => 'created_at',
            'customer_name' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('users')
                        ->select('users.name')
                        ->join('contracts', 'contracts.user_id', '=', 'users.id')
                        ->whereColumn('contracts.id', 'utilities.contract_id')
                        ->limit(1),
                    $direction
                );
            },
            'building_name' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('buildings')
                        ->select('buildings.building_name')
                        ->join('rooms', 'rooms.building_id', '=', 'buildings.id')
                        ->whereColumn('rooms.id', 'utilities.room_id')
                        ->limit(1),
                    $direction
                );
            },
            'room_number' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('rooms')
                        ->select('rooms.room_number')
                        ->whereColumn('rooms.id', 'utilities.room_id')
                        ->limit(1),
                    $direction
                );
            },
        ];
    }

    /**
     * @return array<string, string|Closure>
     */
    public static function invoices(): array
    {
        return [
            'invoice_number' => 'invoice_number',
            'total_amount' => 'total_amount',
            'issued_date' => 'issued_date',
            'due_date' => 'due_date',
            'status' => static function (Builder $query, string $direction): void {
                self::orderByCaseRank($query, $direction, 'invoices.status', [
                    'draft' => 1,
                    'issued' => 2,
                    'unpaid' => 2,
                    'partial' => 3,
                    'paid' => 4,
                    'overdue' => 5,
                    'cancelled' => 6,
                ]);
            },
            'created_at' => 'created_at',
            'customer_name' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('users')
                        ->select('users.name')
                        ->join('contracts', 'contracts.user_id', '=', 'users.id')
                        ->whereColumn('contracts.id', 'invoices.contract_id')
                        ->limit(1),
                    $direction
                );
            },
            'building_name' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('buildings')
                        ->select('buildings.building_name')
                        ->join('rooms', 'rooms.building_id', '=', 'buildings.id')
                        ->join('contracts', 'contracts.room_id', '=', 'rooms.id')
                        ->whereColumn('contracts.id', 'invoices.contract_id')
                        ->limit(1),
                    $direction
                );
            },
            'room_number' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('rooms')
                        ->select('rooms.room_number')
                        ->join('contracts', 'contracts.room_id', '=', 'rooms.id')
                        ->whereColumn('contracts.id', 'invoices.contract_id')
                        ->limit(1),
                    $direction
                );
            },
        ];
    }

    /**
     * @return array<string, string|Closure>
     */
    public static function payments(): array
    {
        return [
            'amount' => 'amount',
            'payment_date' => 'payment_date',
            'status' => static function (Builder $query, string $direction): void {
                self::orderByCaseRank($query, $direction, 'payments.status', [
                    'pending' => 1,
                    'approved' => 2,
                    'rejected' => 3,
                ]);
            },
            'created_at' => 'created_at',
            'invoice_number' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('invoices')
                        ->select('invoices.invoice_number')
                        ->whereColumn('invoices.id', 'payments.invoice_id')
                        ->limit(1),
                    $direction
                );
            },
            'customer_name' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('users')
                        ->select('users.name')
                        ->join('contracts', 'contracts.user_id', '=', 'users.id')
                        ->join('invoices', 'invoices.contract_id', '=', 'contracts.id')
                        ->whereColumn('invoices.id', 'payments.invoice_id')
                        ->limit(1),
                    $direction
                );
            },
            'invoice_amount' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('invoices')
                        ->selectRaw('invoices.total_amount + COALESCE(invoices.late_fee, 0)')
                        ->whereColumn('invoices.id', 'payments.invoice_id')
                        ->limit(1),
                    $direction
                );
            },
            'balance' => static function (Builder $query, string $direction): void {
                $query->orderByRaw(
                    '(
                        COALESCE((
                            SELECT invoices.total_amount + COALESCE(invoices.late_fee, 0)
                            FROM invoices
                            WHERE invoices.id = payments.invoice_id
                            LIMIT 1
                        ), 0)
                        - COALESCE((
                            SELECT SUM(approved.amount)
                            FROM payments AS approved
                            WHERE approved.invoice_id = payments.invoice_id
                              AND approved.status = ?
                        ), 0)
                    ) '.$direction,
                    ['approved']
                );
            },
            'payment_type' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('invoices')
                        ->select('invoices.type')
                        ->whereColumn('invoices.id', 'payments.invoice_id')
                        ->limit(1),
                    $direction
                );
            },
            'payment_method_name' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('payment_methods')
                        ->select('payment_methods.name')
                        ->whereColumn('payment_methods.id', 'payments.payment_method_id')
                        ->limit(1),
                    $direction
                );
            },
        ];
    }

    /**
     * @return array<string, string|Closure>
     */
    public static function receipts(): array
    {
        return [
            'receipt_number' => 'receipt_number',
            'issued_at' => 'issued_at',
            'status' => 'status',
            'created_at' => 'created_at',
            'customer_name' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('users')
                        ->select('users.name')
                        ->join('contracts', 'contracts.user_id', '=', 'users.id')
                        ->join('invoices', 'invoices.contract_id', '=', 'contracts.id')
                        ->join('payments', 'payments.invoice_id', '=', 'invoices.id')
                        ->whereColumn('payments.id', 'receipts.payment_id')
                        ->limit(1),
                    $direction
                );
            },
            'invoice_number' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('invoices')
                        ->select('invoices.invoice_number')
                        ->join('payments', 'payments.invoice_id', '=', 'invoices.id')
                        ->whereColumn('payments.id', 'receipts.payment_id')
                        ->limit(1),
                    $direction
                );
            },
            'paid_amount' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('payments')
                        ->select('payments.amount')
                        ->whereColumn('payments.id', 'receipts.payment_id')
                        ->limit(1),
                    $direction
                );
            },
            'payment_date' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('payments')
                        ->select('payments.payment_date')
                        ->whereColumn('payments.id', 'receipts.payment_id')
                        ->limit(1),
                    $direction
                );
            },
            'payment_method_name' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('payment_methods')
                        ->select('payment_methods.name')
                        ->join('payments', 'payments.payment_method_id', '=', 'payment_methods.id')
                        ->whereColumn('payments.id', 'receipts.payment_id')
                        ->limit(1),
                    $direction
                );
            },
        ];
    }

    /**
     * @return array<string, string|Closure>
     */
    public static function contracts(): array
    {
        return [
            'contract_no' => 'contract_number',
            'contract_number' => 'contract_number',
            'contract_total' => 'contract_total',
            'payment_type' => 'payment_type',
            'start_date' => 'start_date',
            'end_date' => 'end_date',
            'status' => static function (Builder $query, string $direction): void {
                self::orderByCaseRank($query, $direction, 'contracts.status', [
                    'pending' => 1,
                    'rejected' => 2,
                    'active' => 3,
                    'completed' => 4,
                    'terminated' => 5,
                ]);
            },
            'created_at' => 'created_at',
            'customer_name' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('users')
                        ->select('users.name')
                        ->whereColumn('users.id', 'contracts.user_id')
                        ->limit(1),
                    $direction
                );
            },
            'building_name' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('buildings')
                        ->select('buildings.building_name')
                        ->join('rooms', 'rooms.building_id', '=', 'buildings.id')
                        ->whereColumn('rooms.id', 'contracts.room_id')
                        ->limit(1),
                    $direction
                );
            },
            'room_number' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('rooms')
                        ->select('rooms.room_number')
                        ->whereColumn('rooms.id', 'contracts.room_id')
                        ->limit(1),
                    $direction
                );
            },
        ];
    }

    /**
     * @return array<string, string|Closure>
     */
    public static function sales(): array
    {
        return [
            'contract_no' => 'sale_number',
            'sale_number' => 'sale_number',
            'sale_price' => 'sale_price',
            'contract_total' => 'sale_price',
            'payment_type' => 'payment_type',
            'status' => 'status',
            'created_at' => 'created_at',
            'customer_name' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('users')
                        ->select('users.name')
                        ->whereColumn('users.id', 'sales.user_id')
                        ->limit(1),
                    $direction
                );
            },
            'room_number' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('rooms')
                        ->select('rooms.room_number')
                        ->whereColumn('rooms.id', 'sales.room_id')
                        ->limit(1),
                    $direction
                );
            },
        ];
    }

    /**
     * @return array<string, string|Closure>
     */
    public static function maintenanceRequests(): array
    {
        return [
            'category' => 'category',
            'priority' => static function (Builder $query, string $direction): void {
                // Semantic severity: high > medium > low (DESC = highest first).
                self::orderByCaseRank($query, $direction, 'maintenance_requests.priority', [
                    'low' => 1,
                    'medium' => 2,
                    'high' => 3,
                ]);
            },
            'status' => static function (Builder $query, string $direction): void {
                self::orderByCaseRank($query, $direction, 'maintenance_requests.status', [
                    'pending' => 1,
                    'in_progress' => 2,
                    'completed' => 3,
                    'cancelled' => 4,
                    'rejected' => 5,
                ]);
            },
            'created_at' => 'created_at',
            'user_name' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('users')
                        ->select('users.name')
                        ->whereColumn('users.id', 'maintenance_requests.user_id')
                        ->limit(1),
                    $direction
                );
            },
            'room_number' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('rooms')
                        ->select('rooms.room_number')
                        ->whereColumn('rooms.id', 'maintenance_requests.room_id')
                        ->limit(1),
                    $direction
                );
            },
        ];
    }

    /**
     * @return array<string, string|Closure>
     */
    public static function utilityRates(): array
    {
        return [
            'unit_price' => 'unit_price',
            'effective_date' => 'effective_date',
            'status' => 'status',
            'created_at' => 'created_at',
            'type_name' => static function (Builder $query, string $direction): void {
                $query->orderBy(
                    DB::table('utility_types')
                        ->select('utility_types.name')
                        ->whereColumn('utility_types.id', 'utility_rates.utility_type_id')
                        ->limit(1),
                    $direction
                );
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function namedSettings(array $extra = []): array
    {
        return array_merge([
            'name' => 'name',
            'status' => 'status',
            'created_at' => 'created_at',
        ], $extra);
    }
}
