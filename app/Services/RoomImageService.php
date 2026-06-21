<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomImage;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RoomImageService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = RoomImage::query()->with(['room.building']);

        if (! empty($params['room_id'])) {
            $query->where('room_id', (int) $params['room_id']);
        }

        $this->applyListQuery($query, $params, ['image_path', 'description']);

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): RoomImage
    {
        return RoomImage::query()->with(['room.building'])->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): RoomImage
    {
        if (isset($data['image_path'])) {
            $data['image_path'] = $this->normalizeImagePath($data['image_path']);
        }

        $roomImage = RoomImage::query()->create($data);

        if ($roomImage->is_primary) {
            $this->clearOtherPrimaryImages($roomImage->room_id, $roomImage->id);
        }

        return $roomImage->fresh()->load(['room.building']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RoomImage $roomImage, array $data): RoomImage
    {
        $oldPath = $roomImage->image_path;

        if (isset($data['image_path'])) {
            $data['image_path'] = $this->normalizeImagePath($data['image_path']);
        }

        $roomImage->update($data);

        if ($roomImage->is_primary) {
            $this->clearOtherPrimaryImages($roomImage->room_id, $roomImage->id);
        }

        $newPath = $roomImage->image_path;

        if ($oldPath && $newPath && $oldPath !== $newPath) {
            $this->deleteStoredImage($oldPath);
        }

        return $roomImage->fresh()->load(['room.building']);
    }

    public function delete(RoomImage $roomImage): void
    {
        if ($roomImage->image_path) {
            $this->deleteStoredImage($roomImage->image_path);
        }

        $roomImage->delete();
    }

    public function uploadImage(UploadedFile $image, int $roomId): string
    {
        $room = Room::query()->with('building')->findOrFail($roomId);

        $buildingName = $room->building?->building_name ?? 'Unknown Building';
        $roomNumber = $room->room_number;
        $directory = 'buildings/'.$buildingName.'/'.$roomNumber;
        $filename = Str::uuid().'.'.$image->getClientOriginalExtension();

        return $image->storeAs($directory, $filename, 'public');
    }

    public function imageUrl(?string $imagePath): ?string
    {
        $normalizedPath = $this->normalizeImagePath($imagePath);

        if (! $normalizedPath) {
            return null;
        }

        return asset('storage/'.$normalizedPath);
    }

    public function normalizeImagePath(?string $imagePath): ?string
    {
        if (! $imagePath) {
            return null;
        }

        $path = trim($imagePath);

        if (str_starts_with($path, '/storage/')) {
            $path = substr($path, strlen('/storage/'));
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return ltrim($path, '/');
    }

    public function clearOtherPrimaryImages(int $roomId, ?int $exceptImageId = null): void
    {
        $query = RoomImage::query()
            ->where('room_id', $roomId)
            ->where('is_primary', true);

        if ($exceptImageId) {
            $query->where('id', '!=', $exceptImageId);
        }

        $query->update(['is_primary' => false]);
    }

    private function deleteStoredImage(string $imagePath): void
    {
        $normalizedPath = $this->normalizeImagePath($imagePath);

        if ($normalizedPath) {
            Storage::disk('public')->delete($normalizedPath);
        }
    }
}
