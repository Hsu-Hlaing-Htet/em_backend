<?php

namespace App\Services;

use App\Models\RoomImage;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class RoomImageService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = RoomImage::query()->with('room');

        if (! empty($params['room_id'])) {
            $query->where('room_id', $params['room_id']);
        }

        $this->applyListQuery($query, $params, ['description']);

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): RoomImage
    {
        return RoomImage::query()->with('room')->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): RoomImage
    {
        return RoomImage::query()->create($data)->load('room');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RoomImage $roomImage, array $data): RoomImage
    {
        $roomImage->update($data);

        return $roomImage->fresh('room');
    }

    public function delete(RoomImage $roomImage): void
    {
        if ($roomImage->image_path && Storage::disk('public')->exists($roomImage->image_path)) {
            Storage::disk('public')->delete($roomImage->image_path);
        }

        $roomImage->delete();
    }

    /**
     * @return array{image_path: string, image_url: string}
     */
    public function upload(UploadedFile $file, int $roomId): array
    {
        $path = $file->store('rooms', 'public');

        return [
            'image_path' => $path,
            'image_url' => Storage::url($path),
        ];
    }
}
