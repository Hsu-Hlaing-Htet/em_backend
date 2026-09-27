<?php

namespace App\Console\Commands;

use App\Models\RoomImage;
use Database\Seeders\Support\RoomImageSeederSupport;
use Database\Seeders\Support\SeedAssetImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Rematerialize seeded room gallery files onto the public disk.
 *
 * Source: database/seeders/assets/rooms/*
 * Destination: storage/app/public/rooms/... (Laravel public disk)
 * Served as: {APP_URL}/storage/rooms/...
 *
 * Boot-safe: copies files only from existing room_images paths / fallback.
 * Does not mutate contracts, invoices, payments, receipts, or room_images rows.
 */
class SyncSeedRoomImagesCommand extends Command
{
    protected $signature = 'rosewood:sync-seed-room-images {--with-db : Also upsert room_images rows (slower)}';

    protected $description = 'Copy seed room gallery assets onto the public storage disk';

    public function handle(): int
    {
        Artisan::call('storage:link', ['--force' => true]);
        $this->line(trim(Artisan::output()) ?: 'Storage link ensured.');

        File::ensureDirectoryExists(storage_path('app/public/rooms'));
        SeedAssetImage::assertRoomAssetLibraryPresent();
        RoomImageSeederSupport::ensureFallbackImage();

        if ($this->option('with-db')) {
            $this->info('Syncing seeded room images with DB upsert …');
            $upserted = RoomImageSeederSupport::seedAllRooms();
            $this->info("Room image rows ensured: {$upserted}");

            return self::SUCCESS;
        }

        $disk = Storage::disk('public');
        $copied = 0;
        $skipped = 0;

        RoomImage::query()
            ->orderBy('id')
            ->chunkById(100, function ($images) use ($disk, &$copied, &$skipped): void {
                foreach ($images as $image) {
                    $path = ltrim((string) $image->image_path, '/');
                    if ($path === '' || ! SeedAssetImage::isSeededCanonicalRoomPath($path)) {
                        $skipped++;

                        continue;
                    }

                    if (! preg_match('#^rooms/room-(\d+)-(living-room|bedroom|kitchen|bathroom|balcony)\.jpg$#', $path, $matches)) {
                        $skipped++;

                        continue;
                    }

                    $roomId = (int) $matches[1];
                    $slug = $matches[2];
                    $sortOrder = (int) $image->sort_order;
                    $source = RoomImageSeederSupport::sourceAssetFor(
                        new \App\Models\Room(['id' => $roomId]),
                        $slug,
                        $sortOrder,
                    );

                    SeedAssetImage::copyRoomAssetToPublic($source, $path, force: true);
                    $copied++;
                }
            });

        $this->info("Room image files rematerialized: {$copied} (skipped non-canonical: {$skipped})");
        $this->info('Fallback present: '.RoomImageSeederSupport::FALLBACK_PATH);
        $this->info('Public disk root exists: '.($disk->exists('rooms') ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
