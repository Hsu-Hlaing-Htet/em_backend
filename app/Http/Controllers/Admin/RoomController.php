<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkDeleteRoomsRequest;
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
        $this->authorize('viewAny', Room::class);

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
        $this->authorize('create', Room::class);

        $room = $roomService->create($request->validated());

        return response()->json([
            'message' => 'Room created successfully.',
            'data' => new RoomResource($room->load(['building', 'primaryRoomImage'])),
        ], 201);
    }

    public function show(Room $room, RoomService $roomService): JsonResponse
    {
        $this->authorize('view', $room);

        $room = $roomService->find($room->id);

        return response()->json([
            'data' => new RoomResource($room),
        ]);
    }

    public function update(UpdateRoomRequest $request, Room $room, RoomService $roomService): JsonResponse
    {
        $this->authorize('update', $room);

        $room = $roomService->update($room, $request->validated());

        return response()->json([
            'message' => 'Room updated successfully.',
            'data' => new RoomResource($room),
        ]);
    }

    public function destroy(Room $room, RoomService $roomService): JsonResponse
    {
        $this->authorize('delete', $room);

        $hasHistory = $room->contracts()->exists();
        $roomService->delete($room);

        return response()->json([
            'message' => $hasHistory
                ? 'Room marked inactive successfully.'
                : 'Room deleted successfully.',
        ]);
    }

    public function bulkDestroy(BulkDeleteRoomsRequest $request, RoomService $roomService): JsonResponse
    {
        $this->authorize('viewAny', Room::class);

        $deletedCount = $roomService->bulkDelete($request->validated()['ids']);

        return response()->json([
            'message' => "{$deletedCount} rooms deleted successfully.",
            'deleted_count' => $deletedCount,
        ]);
    }

    public function deactivate(Room $room, RoomService $roomService): JsonResponse
    {
        $this->authorize('update', $room);

        return response()->json([
            'message' => 'Room marked inactive successfully.',
            'data' => new RoomResource($roomService->deactivate($room)),
        ]);
    }

    public function activate(Room $room, RoomService $roomService): JsonResponse
    {
        $this->authorize('update', $room);

        return response()->json([
            'message' => 'Room made available successfully.',
            'data' => new RoomResource($roomService->activate($room)),
        ]);
    }
}
