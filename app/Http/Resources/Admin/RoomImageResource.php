<?php

namespace App\Http\Resources\Admin;

use App\Services\RoomImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\RoomImage
 */
class RoomImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imagePath = app(RoomImageService::class)->normalizeImagePath($this->image_path);

        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'room_number' => $this->whenLoaded('room', fn () => $this->room?->room_number),
            'image_path' => $imagePath,
            'image_url' => $imagePath ? asset('storage/'.$imagePath) : null,
            'description' => $this->description,
            'is_primary' => $this->is_primary,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
