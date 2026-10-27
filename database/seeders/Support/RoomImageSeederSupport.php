<?php

namespace Database\Seeders\Support;

use App\Models\Room;
use App\Models\RoomImage;
use Illuminate\Support\Facades\Storage;

final class RoomImageSeederSupport
{
    public const FALLBACK_PATH = 'rooms/fallback-room.jpg';

    /**
     * Return 2–4 gallery definitions for a room.
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
            $all[3] = [
                'slug' => 'balcony',
                'label' => 'Balcony',
            ];
        }

        return array_slice($all, 0, $count);
    }

    public static function pathFor(Room $room, string $slug): string
    {
        return "rooms/room-{$room->id}-{$slug}.jpg";
    }

    public static function sourceAssetFor(
        Room $room,
        string $slug,
        int $sortOrder
    ): string {
        $variants = ['01', '02', '03'];

        $variant = $variants[
            ($room->id + $sortOrder) % count($variants)
        ];

        return "{$slug}-{$variant}.jpg";
    }

    public static function ensureFallbackImage(): string
    {
        SeedAssetImage::assertRoomAssetLibraryPresent();

        $disk = Storage::disk('public');

        if (! $disk->exists(self::FALLBACK_PATH)) {
            return SeedAssetImage::copyRoomAssetToPublic(
                'fallback-room.jpg',
                self::FALLBACK_PATH,
                force: false
            );
        }

        return self::FALLBACK_PATH;
    }

    /**
     * Identify paths owned by the room image seeder.
     */
    public static function isCanonicalSeedPath(
        Room $room,
        string $path
    ): bool {
        $prefix = preg_quote(
            "rooms/room-{$room->id}-",
            '#'
        );

        return (bool) preg_match(
            "#^{$prefix}(living-room|bedroom|kitchen|bathroom|balcony)\.jpg$#",
            ltrim($path, '/')
        );
    }

    /**
     * Seed images without deleting manually uploaded files.
     */
    public static function seedRoom(Room $room): int
    {
        SeedAssetImage::assertRoomAssetLibraryPresent();

        $disk = Storage::disk('public');
        $definitions = self::galleryDefinitions($room);

        $canonicalPaths = [];
        $filled = 0;

        foreach ($definitions as $index => $definition) {
            $slug = $definition['slug'];

            $path = self::pathFor($room, $slug);

            $source = self::sourceAssetFor(
                $room,
                $slug,
                $index
            );

            $canonicalPaths[] = $path;

            // Copy only if the destination is missing or
            // is a known placeholder belonging to the seeder.
            $needsCopy = ! $disk->exists($path);

            if (! $needsCopy) {
                $needsCopy = SeedAssetImage::isPlaceholderFile(
                    $disk->path($path)
                );
            }

            if ($needsCopy) {
                SeedAssetImage::copyRoomAssetToPublic(
                    $source,
                    $path,
                    force: true
                );
            }

            // Reuse or restore a previously seeded image row.
            $image = RoomImage::withTrashed()
                ->where('room_id', $room->id)
                ->where('image_path', $path)
                ->first();

            if ($image) {
                if ($image->trashed()) {
                    $image->restore();
                }

                $image->update([
                    'description' => $definition['label'],
                    'sort_order' => $index,
                ]);
            } else {
                RoomImage::create([
                    'room_id' => $room->id,
                    'image_path' => $path,
                    'description' => $definition['label'],
                    'sort_order' => $index,
                    'is_primary' => false,
                ]);
            }

            $filled++;
        }

        // Soft-delete only obsolete canonical seeded rows.
        // Never delete the physical image files here.
        RoomImage::query()
            ->where('room_id', $room->id)
            ->get()
            ->each(function ($image) use ($room, $canonicalPaths) {
                $path = ltrim(
                    (string) $image->image_path,
                    '/'
                );

                if (
                    self::isCanonicalSeedPath($room, $path)
                    && ! in_array($path, $canonicalPaths, true)
                ) {
                    $image->delete();
                }
            });

        self::ensureSinglePrimaryImage($room->id);

        return $filled;
    }

    /**
     * Maintain exactly one primary image.
     * Prefer an existing manually uploaded primary.
     */
    public static function ensureSinglePrimaryImage(int $roomId): void
    {
        $room = Room::find($roomId);

        if (! $room) {
            return;
        }

        $images = RoomImage::query()
            ->where('room_id', $roomId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($images->isEmpty()) {
            return;
        }

        // Preserve a manually selected primary where possible.
        $primary = $images->first(function ($image) use ($room) {
            return $image->is_primary
                && ! self::isCanonicalSeedPath(
                    $room,
                    (string) $image->image_path
                );
        });

        // Otherwise prefer the first canonical seeded image.
        if (! $primary) {
            $primary = $images->first(function ($image) use ($room) {
                return self::isCanonicalSeedPath(
                    $room,
                    (string) $image->image_path
                );
            });
        }

        $primary ??= $images->first();

        RoomImage::query()
            ->where('room_id', $roomId)
            ->where('is_primary', true)
            ->where('id', '!=', $primary->id)
            ->update(['is_primary' => false]);

        if (! $primary->is_primary) {
            $primary->update(['is_primary' => true]);
        }
    }

    /**
     * Seed existing rooms without modifying other modules.
     */
    public static function seedAllRooms(): int
    {
        SeedAssetImage::assertRoomAssetLibraryPresent();

        self::ensureFallbackImage();

        $total = 0;

        Room::query()
            ->orderBy('id')
            ->chunkById(50, function ($rooms) use (&$total): void {
                foreach ($rooms as $room) {
                    $total += self::seedRoom($room);
                }
            });

        // Do not globally force-delete soft-deleted images.

        return $total;
    }
}
