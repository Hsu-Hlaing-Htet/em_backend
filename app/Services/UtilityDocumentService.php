<?php

namespace App\Services;

use App\Mail\UtilityDocumentMail;
use App\Models\Contract;
use App\Models\Utility;
use App\Services\Concerns\BuildsBillingDocumentData;
use App\Services\Concerns\ServesHtmlDocument;
use App\Support\DocumentFilename;
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
        return $this->downloadPdfResponse(
            $this->renderHtml($utility),
            $this->filename($utility),
        );
    }

    public function exportResponse(Utility $utility): Response
    {
        return $this->exportHtmlResponse(
            $this->renderHtml($utility),
            $this->htmlFilename($utility),
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

        $filename = $this->filename($utility);

        Mail::to($email)->send(new UtilityDocumentMail(
            $utility,
            $this->renderPdfBinary($this->renderHtml($utility)),
            $filename,
            $this->referenceNumber($utility),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDocumentData(Utility $utility): array
    {
        $room = $utility->room;
        $occupant = $this->resolveOccupant($utility);
        $billingMonth = optional($utility->billing_month)->format('F Y') ?? '-';
        $createdAt = optional($utility->created_at)->format('d M Y, H:i') ?? '-';
        $approvedBy = $utility->approver?->name ?? '—';

        return [
            'header' => [
                'referenceNo' => $this->referenceNumber($utility),
                'issuedDate' => optional($utility->approved_at ?? $utility->created_at)->format('Y-m-d H:i') ?? '-',
            ],
            'footerAddress' => $this->footerAddress(),
            'customerInfo' => [
                'name' => $this->customerName($occupant),
                'address' => $occupant?->profile?->address ?? '-',
                'phone' => $occupant?->profile?->phone ?? '-',
                'email' => $occupant?->email ?? '-',
                'building' => $room?->building?->building_name ?? '-',
                'room' => $room?->room_number ?? '-',
                'issuedDate' => optional($utility->approved_at ?? $utility->created_at)->format('d M Y, H:i') ?? '-',
            ],
            'summaryNote' => sprintf(
                'This Utility is for the month of %s and was created on %s by %s and approved by %s.',
                $billingMonth,
                $createdAt,
                $utility->creator?->name ?? '-',
                $approvedBy,
            ),
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
        return rtrim(DocumentFilename::utility(
            $utility->billing_month,
            $utility->room?->room_number ?? (string) $utility->room_id,
        ), '.pdf');
    }

    private function filename(Utility $utility): string
    {
        $utility->loadMissing(['room']);

        return DocumentFilename::utility(
            $utility->billing_month,
            $utility->room?->room_number ?? (string) $utility->room_id,
        );
    }

    private function htmlFilename(Utility $utility): string
    {
        return $this->referenceNumber($utility).'.html';
    }
}
