<?php

namespace Database\Seeders\Support;

use App\Models\Room;
use App\Models\RoomImage;
use Illuminate\Support\Facades\Storage;

final class RoomImageSeederSupport
{
    public const FALLBACK_PATH = 'rooms/fallback-room.jpg';

    /**
     * @return list<array{slug: string, label: string}>
     */
    public static function galleryDefinitions(Room $room): array
    {
        $all = [
            ['slug' => 'living-room', 'label' => 'Living Room'],
            ['slug' => 'bedroom', 'label' => 'Bedroom'],
            ['slug' => 'kitchen', 'label' => 'Kitchen'],
            ['slug' => 'bathroom', 'label' => 'Bathroom'],
        ];

        $count = 2 + ($room->id % 3);

        if ($count === 4 && $room->id % 2 === 0) {
            $all[3] = ['slug' => 'balcony', 'label' => 'Balcony'];
        }

        return array_slice($all, 0, $count);
    }

    public static function pathFor(Room $room, string $slug): string
    {
        return 'rooms/room-'.$room->id.'-'.$slug.'.jpg';
    }

    public static function sourceAssetFor(Room $room, string $slug, int $sortOrder): string
    {
        $variants = ['01', '02', '03'];
        $variant = $variants[($room->id + $sortOrder) % count($variants)];

        return "{$slug}-{$variant}.jpg";
    }

    public static function ensureFallbackImage(): string
    {
        SeedAssetImage::assertRoomAssetLibraryPresent();

        return SeedAssetImage::copyRoomAssetToPublic('fallback-room.jpg', self::FALLBACK_PATH, force: true);
    }

    public static function seedRoom(Room $room): int
    {
        self::ensureFallbackImage();
        SeedAssetImage::assertRoomAssetLibraryPresent();
        $created = 0;

        foreach (self::galleryDefinitions($room) as $index => $definition) {
            $path = self::pathFor($room, $definition['slug']);
            $source = self::sourceAssetFor($room, $definition['slug'], $index);
            SeedAssetImage::copyRoomAssetToPublic($source, $path, force: true);

            RoomImage::query()->updateOrCreate(
                [
                    'room_id' => $room->id,
                    'image_path' => $path,
                ],
                [
                    'description' => $definition['label'],
                    'sort_order' => $index,
                    'is_primary' => $index === 0,
                ],
            );
            $created++;
        }

        self::repairBrokenImages($room->fresh('roomImages'));
        self::cleanupStaleSeededImages($room->fresh('roomImages'));
        self::cleanupObsoleteCanonicalImages($room->fresh('roomImages'));
        self::ensureSinglePrimaryImage($room->id);

        return $created;
    }

    public static function isManualUploadPath(string $path): bool
    {
        return (bool) preg_match('#^buildings/.+/[0-9a-f-]{36}\.(jpg|jpeg|png|webp)$#i', ltrim($path, '/'));
    }

    public static function isLegacySeededBuildingPath(string $path): bool
    {
        return (bool) preg_match('#^buildings/.+/(living-room|master-bedroom|bedroom|kitchen|bathroom)\.jpg$#i', ltrim($path, '/'));
    }

    public static function cleanupStaleSeededImages(Room $room): void
    {
        $disk = Storage::disk('public');
        $canonicalPaths = collect(self::galleryDefinitions($room))
            ->map(fn (array $definition): string => self::pathFor($room, $definition['slug']))
            ->all();

        foreach ($room->roomImages as $image) {
            $path = ltrim((string) $image->image_path, '/');

            if ($path === '' || in_array($path, $canonicalPaths, true) || self::isManualUploadPath($path)) {
                continue;
            }

            $absolutePath = $disk->path($path);
            $shouldRemove = self::isLegacySeededBuildingPath($path)
                || ($disk->exists($path) && SeedAssetImage::isPlaceholderFile($absolutePath));

            if (! $shouldRemove) {
                continue;
            }

            if ($disk->exists($path)) {
                $disk->delete($path);
            }

            $image->forceDelete();
        }
    }

    public static function cleanupObsoleteCanonicalImages(Room $room): void
    {
        $disk = Storage::disk('public');
        $allowedPaths = collect(self::galleryDefinitions($room))
            ->map(fn (array $definition): string => self::pathFor($room, $definition['slug']))
            ->all();

        foreach ($room->roomImages as $image) {
            $path = ltrim((string) $image->image_path, '/');

            if (! preg_match('#^rooms/room-'.$room->id.'-(living-room|bedroom|kitchen|bathroom|balcony)\\.jpg$#', $path)) {
                continue;
            }

            if (in_array($path, $allowedPaths, true)) {
                continue;
            }

            if ($disk->exists($path)) {
                $disk->delete($path);
            }

            $image->forceDelete();
        }
    }

    public static function ensureSinglePrimaryImage(int $roomId): void
    {
        RoomImage::withTrashed()
            ->where('room_id', $roomId)
            ->update(['is_primary' => false]);

        $primaryId = RoomImage::query()
            ->where('room_id', $roomId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');

        if ($primaryId) {
            RoomImage::query()->where('id', $primaryId)->update(['is_primary' => true]);
        }
    }

    public static function repairBrokenImages(Room $room): void
    {
        $disk = Storage::disk('public');
        $definitions = self::galleryDefinitions($room);

        foreach ($room->roomImages as $image) {
            $path = ltrim((string) $image->image_path, '/');

            if ($path !== '' && $disk->exists($path)) {
                continue;
            }

            $sortOrder = (int) $image->sort_order;

            if (! isset($definitions[$sortOrder])) {
                continue;
            }

            $canonical = self::pathFor($room, $definitions[$sortOrder]['slug']);
            $source = self::sourceAssetFor($room, $definitions[$sortOrder]['slug'], $sortOrder);
            SeedAssetImage::copyRoomAssetToPublic($source, $canonical, force: true);

            $image->update([
                'image_path' => $canonical,
                'description' => $definitions[$sortOrder]['label'],
            ]);
        }
    }

    public static function seedAllRooms(): int
    {
        $total = 0;

        Room::query()->with('roomImages')->orderBy('id')->chunkById(50, function ($rooms) use (&$total): void {
            foreach ($rooms as $room) {
                $total += self::seedRoom($room);
            }
        });

        RoomImage::onlyTrashed()->forceDelete();

        return $total;
    }
}
