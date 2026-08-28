<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\User;
use App\Models\Utility;
use App\Support\PhoneNumber;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Support\MyanmarSampleData;
use Database\Seeders\Support\RoomImageSeederSupport;
use Database\Seeders\Support\SeedAssetImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('seeded rooms have valid image records and files', function (): void {
    $this->seed(DatabaseSeeder::class);

    $rooms = Room::query()->with('roomImages')->get();

    expect($rooms->count())->toBeGreaterThan(0);

    foreach ($rooms as $room) {
        expect($room->roomImages->count())->toBeGreaterThanOrEqual(2);
        expect($room->roomImages->count())->toBeLessThanOrEqual(4);

        foreach ($room->roomImages as $image) {
            expect($image->image_path)->toStartWith('rooms/room-'.$room->id.'-');
            expect(Storage::disk('public')->exists($image->image_path))->toBeTrue();
        }
    }
});

test('seeded fake data uses readable emails phones buildings and rooms', function (): void {
    $this->seed(DatabaseSeeder::class);

    $superAdmin = User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
    expect($superAdmin->name)->toBe('U Kyaw Swar');

    $users = User::query()
        ->with('profile')
        ->where('email', '!=', 'admin@rosewoodroyale.com')
        ->get();

    foreach ($users as $user) {
        expect($user->email)->toEndWith('@gmail.com');
        expect($user->email)->not->toMatch('/^(bulk|test|user)\d+@/i');
        expect($user->email)->not->toContain('@example.com');

        $base = MyanmarSampleData::emailBaseForName($user->name);
        expect($user->email)->toMatch('/^'.preg_quote($base, '/').'\d*@gmail\.com$/');
        expect(PhoneNumber::isValid((string) $user->profile?->phone))->toBeTrue();
    }

    expect($users->pluck('email')->duplicates()->isEmpty())->toBeTrue();
    expect($users->pluck('profile.phone')->duplicates()->isEmpty())->toBeTrue();

    $buildingNames = \App\Models\Building::query()->pluck('building_name');

    expect($buildingNames->duplicates()->isEmpty())->toBeTrue();
    expect($buildingNames->all())
        ->each->not->toMatch('/^Building [A-Z]+$/');
    expect($buildingNames->all())
        ->each->toMatch('/(Residence|Tower|Heights|Court)$/');

    expect(Room::query()->pluck('room_number')->all())
        ->each->toMatch('/^[A-Z]-\d{3}$/');

    expect(Room::query()->where('room_number', 'A-101')->exists())->toBeTrue();
    expect(Room::query()->where('room_number', 'B-101')->exists())->toBeTrue();
    expect(Room::query()->where('room_number', 'C-101')->exists())->toBeTrue();
    expect(Room::query()->where('room_number', 'D-101')->exists())->toBeTrue();
});

test('room image api returns browser accessible urls', function (): void {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
    $room = Room::query()->with('roomImages')->firstOrFail();

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson("/api/rooms/{$room->id}")
        ->assertOk();

    $images = collect($response->json('data.room_images'));
    expect($images)->not->toBeEmpty();

    foreach ($images as $image) {
        expect($image['image_url'])->toContain('/storage/rooms/room-'.$room->id.'-');
    }
});

test('workflow consolidated invoice contains rent and utility line items', function (): void {
    $this->seed(DatabaseSeeder::class);

    $invoice = Invoice::query()->where('invoice_number', 'INV-000007')->with('items')->firstOrFail();

    expect($invoice->billing_month)->not->toBeNull();
    expect($invoice->items->count())->toBeGreaterThanOrEqual(4);
    expect(round((float) $invoice->total_amount, 2))
        ->toBe(round((float) $invoice->items->sum('amount'), 2));
});

test('utility records are linked once to consolidated invoices', function (): void {
    $this->seed(DatabaseSeeder::class);

    $linked = Utility::query()->whereNotNull('invoice_id')->get();

    expect($linked->count())->toBeGreaterThan(0);
    expect($linked->pluck('invoice_id')->duplicates()->isEmpty())->toBeTrue();
});

test('payment and receipt demo integrity rules hold', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(
        Payment::query()->whereIn('status', ['pending', 'rejected'])->whereHas('receipt')->count()
    )->toBe(0);

    expect(
        Receipt::query()->select('payment_id')->groupBy('payment_id')->havingRaw('COUNT(*) > 1')->count()
    )->toBe(0);

    $paidInvoice = Invoice::query()->where('invoice_number', 'INV-000005')->with('payments.receipt')->firstOrFail();
    $payment = $paidInvoice->payments->first();
    expect($payment)->not->toBeNull();
    expect((float) $payment->amount)->toBe(round((float) $paidInvoice->total_amount + (float) $paidInvoice->late_fee, 2));
    expect($payment->receipt)->not->toBeNull();
    expect($payment->receipt->sent_at)->not->toBeNull();
});

test('repeated seeding does not duplicate room images invoices or receipts', function (): void {
    Artisan::call('db:seed', ['--force' => true]);
    $first = [
        'rooms' => Room::query()->count(),
        'room_images' => RoomImage::query()->count(),
        'invoices' => Invoice::query()->count(),
        'payments' => Payment::query()->count(),
        'receipts' => Receipt::query()->count(),
    ];

    Artisan::call('db:seed', ['--force' => true]);
    $second = [
        'rooms' => Room::query()->count(),
        'room_images' => RoomImage::query()->count(),
        'invoices' => Invoice::query()->count(),
        'payments' => Payment::query()->count(),
        'receipts' => Receipt::query()->count(),
    ];

    expect($second)->toBe($first);
});

test('seeded room image files are valid photos not placeholders', function (): void {
    $this->seed(DatabaseSeeder::class);

    $rooms = Room::query()->with('roomImages')->get();
    $checkedPaths = [];

    foreach ($rooms as $room) {
        $roomPaths = $room->roomImages->pluck('image_path')->unique()->values();

        expect($roomPaths->count())->toBe($room->roomImages->count());

        foreach ($room->roomImages as $image) {
            $absolutePath = Storage::disk('public')->path($image->image_path);
            $info = SeedAssetImage::inspect($absolutePath);
            SeedAssetImage::assertMimeMatchesExtension($absolutePath, $info);
            SeedAssetImage::assertValidRoomAsset($absolutePath);

            expect($info[0])->toBeGreaterThanOrEqual(SeedAssetImage::MIN_ROOM_WIDTH);
            expect($info[1])->toBeGreaterThanOrEqual(SeedAssetImage::MIN_ROOM_HEIGHT);
            expect(filesize($absolutePath))->toBeGreaterThan(SeedAssetImage::MIN_FILE_BYTES);
            expect(SeedAssetImage::isPlaceholderFile($absolutePath))->toBeFalse();
            expect(SeedAssetImage::isMarketingBannerFile($absolutePath))->toBeFalse();

            $checkedPaths[] = $image->image_path;
        }
    }

    expect(count(array_unique($checkedPaths)))->toBeGreaterThan(0);
});

test('seeded room images do not include marketing banner assets', function (): void {
    $this->seed(DatabaseSeeder::class);

    $bannerMatches = 0;

    foreach (glob(storage_path('app/public/rooms/*.jpg')) ?: [] as $absolutePath) {
        if (SeedAssetImage::isMarketingBannerFile($absolutePath)) {
            $bannerMatches++;
        }
    }

    expect($bannerMatches)->toBe(0);

    $contactBannerPath = base_path('../frontend/src/assets/images/contact_banner.png');

    if (is_file($contactBannerPath)) {
        $contactHash = md5_file($contactBannerPath) ?: '';

        foreach (RoomImage::query()->pluck('image_path') as $relativePath) {
            $absolutePath = Storage::disk('public')->path($relativePath);

            if (is_file($absolutePath) && md5_file($absolutePath) === $contactHash) {
                $bannerMatches++;
            }
        }
    }

    expect($bannerMatches)->toBe(0);
});

test('seeded payment proof files are valid images', function (): void {
    $this->seed(DatabaseSeeder::class);

    $proofPaths = Payment::query()
        ->whereNotNull('proof_image_path')
        ->pluck('proof_image_path')
        ->unique()
        ->filter(fn (string $path): bool => str_starts_with($path, 'payments/'));

    expect($proofPaths->count())->toBeGreaterThan(0);

    foreach ($proofPaths as $path) {
        $absolutePath = Storage::disk('public')->path($path);
        $info = SeedAssetImage::inspect($absolutePath);
        SeedAssetImage::assertMimeMatchesExtension($absolutePath, $info);
        expect(filesize($absolutePath))->toBeGreaterThan(SeedAssetImage::MIN_FILE_BYTES);
        expect(SeedAssetImage::isPlaceholderFile($absolutePath))->toBeFalse();
    }
});

test('room image seeder support uses deterministic canonical paths', function (): void {
    $room = Room::query()->create([
        'building_id' => \App\Models\Building::query()->create([
            'building_name' => 'Path Test Tower',
            'location' => 'Kamayut Township, Yangon',
        ])->id,
        'room_number' => 'PT-01',
        'floor_number' => 1,
        'type' => 'rent',
        'status' => 'available',
        'area_sqft' => 800,
        'sale_price' => 0,
        'rent_price' => 400000,
        'rent_deposit_price' => 40000,
        'booking_deposit_price' => 0,
    ]);

    RoomImageSeederSupport::seedRoom($room);

    $paths = RoomImage::query()->where('room_id', $room->id)->pluck('image_path');
    expect($paths->first())->toBe(RoomImageSeederSupport::pathFor($room, 'living-room'));
    expect(Storage::disk('public')->exists($paths->first()))->toBeTrue();
});
