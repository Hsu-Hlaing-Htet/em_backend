<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoomImageRequest;
use App\Http\Requests\Admin\UpdateRoomImageRequest;
use App\Http\Requests\Admin\UploadRoomImageRequest;
use App\Http\Resources\Admin\RoomImageResource;
use App\Models\RoomImage;
use App\Services\RoomImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomImageController extends Controller
{
    public function index(Request $request, RoomImageService $roomImageService): JsonResponse
    {
        $this->authorize('viewAny', RoomImage::class);

        $paginator = $roomImageService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => RoomImageResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreRoomImageRequest $request, RoomImageService $roomImageService): JsonResponse
    {
        $this->authorize('create', RoomImage::class);

        $roomImage = $roomImageService->create($request->validated());

        return response()->json([
            'message' => 'Room image created successfully.',
            'data' => new RoomImageResource($roomImage->load(['room.building'])),
        ], 201);
    }

    public function show(RoomImage $roomImage): JsonResponse
    {
        $this->authorize('view', $roomImage);

        return response()->json([
            'data' => new RoomImageResource($roomImage->load(['room.building'])),
        ]);
    }

    public function update(UpdateRoomImageRequest $request, RoomImage $roomImage, RoomImageService $roomImageService): JsonResponse
    {
        $this->authorize('update', $roomImage);

        $roomImage = $roomImageService->update($roomImage, $request->validated());

        return response()->json([
            'message' => 'Room image updated successfully.',
            'data' => new RoomImageResource($roomImage->load(['room.building'])),
        ]);
    }

    public function upload(UploadRoomImageRequest $request, RoomImageService $roomImageService): JsonResponse
    {
        $this->authorize('create', RoomImage::class);

        $imagePath = $roomImageService->uploadImage(
            $request->file('image'),
            (int) $request->validated('room_id'),
        );

        return response()->json([
            'message' => 'Image uploaded successfully.',
            'data' => [
                'image_path' => $imagePath,
                'image_url' => $roomImageService->imageUrl($imagePath),
            ],
        ]);
    }

    public function destroy(RoomImage $roomImage, RoomImageService $roomImageService): JsonResponse
    {
        $this->authorize('delete', $roomImage);

        $roomImageService->delete($roomImage);

        return response()->json([
            'message' => 'Room image deleted successfully.',
        ]);
    }
}
