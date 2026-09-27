<?php

namespace App\Console\Commands;

use Database\Seeders\Support\RoomImageSeederSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Rematerialize seeded room gallery files onto the public disk.
 *
 * Source: database/seeders/assets/rooms/*
 * Destination: storage/app/public/rooms/room-{id}-{slug}.jpg (Laravel public disk)
 * Served as: {APP_URL}/storage/rooms/...
 *
 * Safe/idempotent: updateOrCreate on room_images + overwrite canonical seed files.
 * Does not touch contracts, invoices, payments, or receipts.
 */
class SyncSeedRoomImagesCommand extends Command
{
    protected $signature = 'rosewood:sync-seed-room-images';

    protected $description = 'Copy seed room gallery assets onto the public storage disk and ensure DB image rows';

    public function handle(): int
    {
        Artisan::call('storage:link', ['--force' => true]);
        $this->line(trim(Artisan::output()) ?: 'Storage link ensured.');

        $publicRoot = storage_path('app/public');
        File::ensureDirectoryExists($publicRoot.'/rooms');

        $this->info('Syncing seeded room images from database/seeders/assets/rooms …');
        $upserted = RoomImageSeederSupport::seedAllRooms();
        $this->info("Room image rows ensured: {$upserted}");

        $fallback = storage_path('app/public/'.RoomImageSeederSupport::FALLBACK_PATH);
        if (! is_file($fallback)) {
            $this->error('Fallback room image missing after sync: '.$fallback);

            return self::FAILURE;
        }

        $this->info('Fallback present: '.RoomImageSeederSupport::FALLBACK_PATH);

        return self::SUCCESS;
    }
}
