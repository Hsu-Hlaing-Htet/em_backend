<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoomRequest;
use App\Http\Requests\Admin\UpdateRoomRequest;
use App\Http\Resources\Admin\RoomResource;
use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request, RoomService $roomService): JsonResponse
    {
        $paginator = $roomService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => RoomResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreRoomRequest $request, RoomService $roomService): JsonResponse
    {
        $room = $roomService->create($request->validated());

        return response()->json([
            'message' => 'Room created successfully.',
            'data' => new RoomResource($room),
        ], 201);
    }

    public function show(Room $room): JsonResponse
    {
        $room->load(['building', 'roomImages']);

        return response()->json([
            'data' => new RoomResource($room),
        ]);
    }

    public function update(UpdateRoomRequest $request, Room $room, RoomService $roomService): JsonResponse
    {
        $room = $roomService->update($room, $request->validated());

        return response()->json([
            'message' => 'Room updated successfully.',
            'data' => new RoomResource($room),
        ]);
    }

    public function destroy(Room $room, RoomService $roomService): JsonResponse
    {
        $roomService->delete($room);

        return response()->json([
            'message' => 'Room deleted successfully.',
        ]);
    }
}
