<?php

namespace App\Http\Resources\Admin;

use App\Services\RoomImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Room
 */
class RoomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roomImageService = app(RoomImageService::class);

        return [
            'id' => $this->id,
            'building_id' => $this->building_id,
            'building_name' => $this->whenLoaded('building', fn () => $this->building?->building_name),
            'room_number' => $this->room_number,
            'floor_number' => $this->floor_number,
            'area_sqft' => $this->area_sqft,
            'description' => $this->description,
            'type' => $this->type,
            'status' => $this->status,
            'sale_price' => $this->sale_price,
            'rent_price' => $this->rent_price,
            'rent_deposit_price' => $this->rent_deposit_price,
            'booking_deposit_price' => $this->booking_deposit_price,
            'primary_image_url' => $this->resolvePrimaryImageUrl($roomImageService),
            'room_images' => $this->whenLoaded(
                'roomImages',
                fn () => RoomImageResource::collection($this->roomImages),
            ),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    private function resolvePrimaryImageUrl(RoomImageService $roomImageService): ?string
    {
        if ($this->relationLoaded('primaryRoomImage') && $this->primaryRoomImage) {
            return $roomImageService->imageUrl($this->primaryRoomImage->image_path);
        }

        if ($this->relationLoaded('roomImages') && $this->roomImages->isNotEmpty()) {
            $primaryImage = $this->roomImages->firstWhere('is_primary', true)
                ?? $this->roomImages->sortBy([['sort_order', 'asc'], ['id', 'asc']])->first();

            return $roomImageService->imageUrl($primaryImage?->image_path);
        }

        return null;
    }
}
