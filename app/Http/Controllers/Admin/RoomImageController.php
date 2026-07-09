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
        $roomImage = $roomImageService->create($request->validated());

        return response()->json([
            'message' => 'Room image created successfully.',
            'data' => new RoomImageResource($roomImage),
        ], 201);
    }

    public function upload(UploadRoomImageRequest $request, RoomImageService $roomImageService): JsonResponse
    {
        $upload = $roomImageService->upload(
            $request->file('image'),
            (int) $request->validated('room_id')
        );

        return response()->json([
            'data' => $upload,
        ]);
    }

    public function show(RoomImage $roomImage): JsonResponse
    {
        $roomImage->load('room');

        return response()->json([
            'data' => new RoomImageResource($roomImage),
        ]);
    }

    public function update(UpdateRoomImageRequest $request, RoomImage $roomImage, RoomImageService $roomImageService): JsonResponse
    {
        $roomImage = $roomImageService->update($roomImage, $request->validated());

        return response()->json([
            'message' => 'Room image updated successfully.',
            'data' => new RoomImageResource($roomImage),
        ]);
    }

    public function destroy(RoomImage $roomImage, RoomImageService $roomImageService): JsonResponse
    {
        $roomImageService->delete($roomImage);

        return response()->json([
            'message' => 'Room image deleted successfully.',
        ]);
    }
}
