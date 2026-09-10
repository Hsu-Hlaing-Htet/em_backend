<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\RoomImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicPropertyController extends Controller
{
    public function index(Request $request, RoomImageService $imageService): JsonResponse
    {
        $query = Room::query()
            ->with(['building', 'roomImages', 'primaryRoomImage'])
            ->where('status', Room::STATUS_AVAILABLE);

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search): void {
                $q->where('room_number', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhereHas('building', function ($bq) use ($search): void {
                        $bq->where('building_name', 'like', $search)
                            ->orWhere('location', 'like', $search);
                    });
            });
        }

        if ($request->filled('purpose')) {
            $purpose = strtolower((string) $request->input('purpose'));
            if ($purpose === 'rent') {
                $query->whereIn('type', [Room::TYPE_RENT, Room::TYPE_BOTH])
                    ->where('rent_price', '>', 0);
            } elseif ($purpose === 'sale') {
                $query->whereIn('type', [Room::TYPE_SALE, Room::TYPE_BOTH])
                    ->where('sale_price', '>', 0);
            }
        }

        if ($request->filled('building_id')) {
            $query->where('building_id', (int) $request->input('building_id'));
        }

        if ($request->filled('min_price')) {
            $min = (float) $request->input('min_price');
            $query->where(function ($q) use ($min): void {
                $q->where('rent_price', '>=', $min)
                    ->orWhere('sale_price', '>=', $min);
            });
        }

        if ($request->filled('max_price')) {
            $max = (float) $request->input('max_price');
            $query->where(function ($q) use ($max): void {
                $q->where('rent_price', '<=', $max)
                    ->orWhere('sale_price', '<=', $max);
            });
        }

        $perPage = (int) $request->input('per_page', 20);
        $paginator = $query->orderBy('building_id')->orderBy('floor_number')->paginate($perPage);

        $items = collect($paginator->items())->map(
            fn (Room $room) => $this->formatProperty($room, $imageService)
        )->values()->all();

        return response()->json([
            'data' => [
                'data' => $items,
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function featured(Request $request, RoomImageService $imageService): JsonResponse
    {
        $rooms = Room::query()
            ->with(['building', 'roomImages', 'primaryRoomImage'])
            ->where('status', Room::STATUS_AVAILABLE)
            ->inRandomOrder()
            ->limit(6)
            ->get();

        $items = $rooms->map(fn (Room $room) => $this->formatProperty($room, $imageService))->values()->all();

        return response()->json([
            'data' => $items,
        ]);
    }

    public function show(int $id, RoomImageService $imageService): JsonResponse
    {
        $room = Room::query()
            ->with(['building', 'roomImages', 'primaryRoomImage'])
            ->findOrFail($id);

        return response()->json([
            'data' => $this->formatProperty($room, $imageService),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatProperty(Room $room, RoomImageService $imageService): array
    {
        $buildingName = $room->building?->building_name ?? 'Rosewood Property';
        $location = $room->building?->location ?? 'Yangon';

        $primaryImage = null;
        if ($room->primaryRoomImage) {
            $primaryImage = $imageService->imageUrl($room->primaryRoomImage->image_path);
        } elseif ($room->roomImages->isNotEmpty()) {
            $primaryImage = $imageService->imageUrl($room->roomImages->first()?->image_path);
        }

        $galleryImages = $room->roomImages
            ->map(fn ($img) => $imageService->imageUrl($img->image_path))
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $room->id,
            'property_name' => "{$buildingName} - Unit {$room->room_number}",
            'property_code' => "RR-{$room->building_id}-{$room->room_number}",
            'building_id' => $room->building_id,
            'building_name' => $buildingName,
            'township' => $location,
            'address' => $location,
            'room_number' => $room->room_number,
            'floor_number' => $room->floor_number,
            'type' => $room->type,
            'purpose' => $room->type === Room::TYPE_BOTH ? 'rent' : $room->type,
            'status' => $room->status,
            'monthly_rent' => (float) ($room->rent_price ?? 0),
            'rent_price' => (float) ($room->rent_price ?? 0),
            'sale_price' => (float) ($room->sale_price ?? 0),
            'rent_deposit_price' => (float) ($room->rent_deposit_price ?? 0),
            'booking_deposit_price' => (float) ($room->booking_deposit_price ?? 0),
            'deposit' => (float) (($room->rent_deposit_price ?: $room->booking_deposit_price) ?? 0),
            'area_sqft' => (float) ($room->area_sqft ?? 0),
            'width_ft' => (float) ($room->width_ft ?? 0),
            'length_ft' => (float) ($room->length_ft ?? 0),
            'description' => $room->description,
            'featured_image' => $primaryImage,
            'gallery_images' => $galleryImages,
            'created_at' => $room->created_at?->toIso8601String(),
        ];
    }
}
