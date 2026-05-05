<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\GenerateInvoiceRequest;
use App\Models\Contract;
use App\Models\Invoice;
use App\Services\InvoiceGenerationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceGenerationService $invoiceGenerationService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()->with(['property', 'payments.receipt', 'receipts']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('from')) {
            $query->whereDate('due_date', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('due_date', '<=', $request->date('to'));
        }

        return response()->json($query->latest('id')->paginate((int) $request->integer('per_page', 15)));
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json($invoice->load([
            'property',
            'items',
            'payments.receipt',
            'user',
            'tenant',
        ]));
    }

    public function generate(GenerateInvoiceRequest $request): JsonResponse
    {
        $contract = Contract::query()->with(['owner', 'tenant'])->findOrFail($request->integer('contract_id'));

        $invoices = $this->invoiceGenerationService->generate(
            contract: $contract,
            mode: $request->string('mode')->toString(),
            totalAmount: (float) $request->input('total_amount'),
            firstDueDate: Carbon::parse($request->string('first_due_date')),
            numberOfMonths: (int) ($request->input('number_of_months') ?? $contract->number_of_months ?? 1),
            monthlyDueDay: (int) ($request->input('monthly_due_day') ?? $contract->monthly_due_day ?? 1)
        );

        return response()->json([
            'message' => 'Invoices generated successfully.',
            'data' => $invoices,
        ], 201);
    }

    public function send(Invoice $invoice): JsonResponse
    {
        $invoice->update(['sent_at' => now()]);

        return response()->json([
            'message' => 'Invoice marked as sent.',
            'data' => $invoice,
        ]);
    }
}
