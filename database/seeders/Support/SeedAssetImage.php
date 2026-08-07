<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

final class SeedAssetImage
{
    public const MIN_ROOM_WIDTH = 800;

    public const MIN_ROOM_HEIGHT = 500;

    public const MIN_FILE_BYTES = 20_000;

    public const PLACEHOLDER_MAX_BYTES = 15_000;

    public const PLACEHOLDER_WIDTH = 640;

    public const PLACEHOLDER_HEIGHT = 480;

    /**
     * Known marketing/banner assets that must never appear in room galleries.
     *
     * @var list<string>
     */
    public const BANNED_MARKETING_BANNER_HASHES = [
        'f1d853aeaa3ebc3c31fc65ede45f1386', // contact_banner-derived living-room-01 / fallback
    ];

    public static function assetsBasePath(): string
    {
        return database_path('seeders/assets');
    }

    public static function roomAssetPath(string $filename): string
    {
        return self::assetsBasePath().'/rooms/'.$filename;
    }

    public static function paymentAssetPath(string $filename): string
    {
        return self::assetsBasePath().'/payments/'.$filename;
    }

    /**
     * @return array{0:int,1:int,2:int,mime:string,bits:int,channels:int}
     */
    public static function inspect(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            throw new InvalidArgumentException("Seed asset not found: {$absolutePath}");
        }

        $info = @getimagesize($absolutePath);

        if ($info === false) {
            throw new InvalidArgumentException("Invalid image file: {$absolutePath}");
        }

        return $info;
    }

    public static function isPlaceholderFile(string $absolutePath): bool
    {
        if (! is_file($absolutePath)) {
            return true;
        }

        $info = @getimagesize($absolutePath);

        if ($info === false) {
            return true;
        }

        $bytes = filesize($absolutePath) ?: 0;

        return $bytes <= self::PLACEHOLDER_MAX_BYTES
            && ($info[0] ?? 0) === self::PLACEHOLDER_WIDTH
            && ($info[1] ?? 0) === self::PLACEHOLDER_HEIGHT;
    }

    public static function assertValidRoomAsset(string $absolutePath): void
    {
        $info = self::inspect($absolutePath);
        self::assertMimeMatchesExtension($absolutePath, $info);
        $bytes = filesize($absolutePath) ?: 0;

        if ($bytes < self::MIN_FILE_BYTES) {
            throw new InvalidArgumentException("Seed asset too small: {$absolutePath}");
        }

        if (($info[0] ?? 0) < self::MIN_ROOM_WIDTH || ($info[1] ?? 0) < self::MIN_ROOM_HEIGHT) {
            throw new InvalidArgumentException("Seed asset dimensions too small: {$absolutePath}");
        }

        if (! in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new InvalidArgumentException("Unsupported seed asset MIME type: {$absolutePath}");
        }

        if (self::isPlaceholderFile($absolutePath)) {
            throw new InvalidArgumentException("Seed asset appears to be a generated placeholder: {$absolutePath}");
        }

        self::assertNotMarketingBannerAsset($absolutePath);
    }

    public static function assertNotMarketingBannerAsset(string $absolutePath): void
    {
        $hash = md5_file($absolutePath) ?: '';

        if (in_array($hash, self::BANNED_MARKETING_BANNER_HASHES, true)) {
            throw new InvalidArgumentException("Seed asset is a banned marketing/banner image: {$absolutePath}");
        }
    }

    public static function isMarketingBannerFile(string $absolutePath): bool
    {
        if (! is_file($absolutePath)) {
            return false;
        }

        $hash = md5_file($absolutePath) ?: '';

        return in_array($hash, self::BANNED_MARKETING_BANNER_HASHES, true);
    }

    /**
     * @param  array{0:int,1:int,2:int,mime:string,bits:int,channels:int}  $info
     */
    public static function assertMimeMatchesExtension(string $absolutePath, ?array $info = null): void
    {
        $info ??= self::inspect($absolutePath);
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $expectedMime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => throw new InvalidArgumentException("Unsupported seed asset extension: {$absolutePath}"),
        };

        if (($info['mime'] ?? '') !== $expectedMime) {
            throw new InvalidArgumentException("MIME type {$info['mime']} does not match .{$extension} for {$absolutePath}");
        }
    }

    public static function copyRoomAssetToPublic(string $sourceFilename, string $destinationRelativePath, bool $force = false): string
    {
        return self::copyAssetToPublic(
            self::roomAssetPath($sourceFilename),
            $destinationRelativePath,
            force: $force,
            validateRoom: true,
        );
    }

    public static function copyPaymentAssetToPublic(string $sourceFilename, string $destinationRelativePath, bool $force = false): string
    {
        return self::copyAssetToPublic(
            self::paymentAssetPath($sourceFilename),
            $destinationRelativePath,
            force: $force,
            validateRoom: false,
        );
    }

    public static function copyAssetToPublic(
        string $sourceAbsolutePath,
        string $destinationRelativePath,
        bool $force = false,
        bool $validateRoom = true,
    ): string {
        if ($validateRoom) {
            self::assertValidRoomAsset($sourceAbsolutePath);
        } else {
            $info = self::inspect($sourceAbsolutePath);
            self::assertMimeMatchesExtension($sourceAbsolutePath, $info);
        }

        $destinationRelativePath = ltrim($destinationRelativePath, '/');
        $disk = Storage::disk('public');
        $destinationAbsolute = $disk->path($destinationRelativePath);

        if (
            ! $force
            && $disk->exists($destinationRelativePath)
            && ! self::isPlaceholderFile($destinationAbsolute)
            && ! self::isSeededCanonicalRoomPath($destinationRelativePath)
        ) {
            return $destinationRelativePath;
        }

        File::ensureDirectoryExists(dirname($destinationAbsolute));
        File::copy($sourceAbsolutePath, $destinationAbsolute);

        if ($validateRoom) {
            self::assertValidRoomAsset($destinationAbsolute);
        }

        return $destinationRelativePath;
    }

    public static function isSeededCanonicalRoomPath(string $relativePath): bool
    {
        return (bool) preg_match('#^rooms/room-\d+-(living-room|bedroom|kitchen|bathroom|balcony)\.jpg$#', $relativePath)
            || $relativePath === RoomImageSeederSupport::FALLBACK_PATH;
    }

    public static function paymentProofSourceForPath(string $destinationRelativePath): string
    {
        if (str_contains($destinationRelativePath, 'pending')) {
            return 'proof-kbz-pay.jpg';
        }

        if (str_contains($destinationRelativePath, 'rejected')) {
            return 'proof-bank-transfer.jpg';
        }

        return 'proof-cash.jpg';
    }

    public static function storePaymentProof(string $relativePath): string
    {
        $source = self::paymentProofSourceForPath($relativePath);
        $extension = pathinfo($source, PATHINFO_EXTENSION) ?: 'jpg';
        $normalized = preg_replace('/\.(jpg|jpeg|png|webp)$/i', '.'.$extension, ltrim($relativePath, '/')) ?? ltrim($relativePath, '/');

        return self::copyPaymentAssetToPublic($source, $normalized, force: true);
    }

    public static function requiredRoomAssets(): array
    {
        $assets = [];

        foreach (['living-room', 'bedroom', 'kitchen', 'bathroom', 'balcony'] as $category) {
            foreach (['01', '02', '03'] as $index) {
                $assets[] = "{$category}-{$index}.jpg";
            }
        }

        $assets[] = 'fallback-room.jpg';

        return $assets;
    }

    public static function assertRoomAssetLibraryPresent(): void
    {
        foreach (self::requiredRoomAssets() as $filename) {
            $path = self::roomAssetPath($filename);

            if (! is_file($path)) {
                throw new RuntimeException("Missing required room seed asset: {$path}");
            }

            self::assertValidRoomAsset($path);
        }

        foreach (['proof-kbz-pay.jpg', 'proof-cash.jpg', 'proof-bank-transfer.jpg'] as $filename) {
            $path = self::paymentAssetPath($filename);

            if (! is_file($path)) {
                throw new RuntimeException("Missing required payment seed asset: {$path}");
            }

            self::inspect($path);
        }
    }
}
