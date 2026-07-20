<?php

namespace App\Services;

use App\Mail\UtilityDocumentMail;
use App\Models\Contract;
use App\Models\Utility;
use App\Services\Concerns\BuildsBillingDocumentData;
use App\Services\Concerns\ServesHtmlDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class UtilityDocumentService
{
    use BuildsBillingDocumentData;
    use ServesHtmlDocument;

    public function find(int $id): Utility
    {
        return Utility::query()
            ->with([
                'room.building',
                'items.utilityType',
                'creator',
                'approver',
            ])
            ->findOrFail($id);
    }

    public function renderHtml(Utility $utility): string
    {
        $utility->loadMissing([
            'room.building',
            'items.utilityType',
            'creator',
            'approver',
        ]);

        return view('utilities.document', [
            'document' => $this->buildDocumentData($utility),
        ])->render();
    }

    public function downloadResponse(Utility $utility): Response
    {
        return $this->downloadHtmlResponse(
            $this->renderHtml($utility),
            $this->filename($utility),
        );
    }

    public function exportResponse(Utility $utility): Response
    {
        return $this->exportHtmlResponse(
            $this->renderHtml($utility),
            $this->filename($utility),
        );
    }

    /**
     * @param  array{email?: string|null}  $data
     */
    public function sendEmail(Utility $utility, array $data): void
    {
        $utility->loadMissing(['room']);
        $occupant = $this->resolveOccupant($utility);

        $email = $data['email'] ?? $occupant?->email;

        if (! $email) {
            throw new InvalidArgumentException('Occupant email is required to send the utility bill document.');
        }

        Mail::to($email)->send(new UtilityDocumentMail(
            $utility,
            $this->renderHtml($utility),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDocumentData(Utility $utility): array
    {
        $room = $utility->room;
        $occupant = $this->resolveOccupant($utility);

        return [
            'header' => [
                'referenceNo' => $this->referenceNumber($utility),
                'issuedDate' => optional($utility->approved_at ?? $utility->created_at)->format('Y-m-d H:i') ?? '-',
            ],
            'footerAddress' => $this->footerAddress(),
            'details' => [
                ['label' => 'Customer', 'value' => $this->customerName($occupant)],
                ['label' => 'Building', 'value' => $room?->building?->building_name ?? '-'],
                ['label' => 'Room / Unit', 'value' => $room?->room_number ?? '-'],
                ['label' => 'Billing Month', 'value' => optional($utility->billing_month)->format('F Y') ?? '-'],
                ['label' => 'Status', 'value' => $this->statusLabel($utility->status)],
                ['label' => 'Prepared By', 'value' => $utility->creator?->name ?? '-'],
                ['label' => 'Approved By', 'value' => $utility->approver?->name ?? 'Pending'],
            ],
            'readings' => $utility->items->map(fn ($item) => [
                'utility_type' => $item->utilityType?->name ?? '-',
                'previous_reading' => number_format((float) $item->previous_reading, 2),
                'current_reading' => number_format((float) $item->current_reading, 2),
                'usage' => number_format((float) $item->usage, 2),
                'unit_price' => $this->formatCurrency((float) $item->unit_price),
                'amount' => $this->formatCurrency((float) $item->amount),
            ])->all(),
            'totalDue' => [
                'label' => 'Total Amount',
                'amount' => $this->formatCurrency((float) $utility->total_amount),
            ],
        ];
    }

    private function resolveOccupant(Utility $utility): ?\App\Models\User
    {
        if (! $utility->room_id) {
            return null;
        }

        $contract = Contract::query()
            ->where('room_id', $utility->room_id)
            ->whereIn('status', ['active', 'approved'])
            ->with('user.profile')
            ->latest()
            ->first();

        return $contract?->user;
    }

    private function referenceNumber(Utility $utility): string
    {
        $month = optional($utility->billing_month)->format('Y-m') ?? '0000-00';
        $room = $utility->room?->room_number ?? $utility->room_id;

        return sprintf('UTL-%s-%s', $month, $room);
    }

    private function filename(Utility $utility): string
    {
        return $this->referenceNumber($utility).'.html';
    }
}
