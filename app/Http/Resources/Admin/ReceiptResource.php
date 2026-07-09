<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Receipt
 */
class ReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'receipt_number' => $this->receipt_number,
            'receipt_pdf_path' => $this->receipt_pdf_path,
            'status' => $this->status,
            'issued_at' => $this->issued_at?->toDateTimeString(),
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
