<?php

namespace App\Http\Resources\Public;

use App\Services\RoomImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public property payload sourced only from Building/Room (and related) data.
 *
 * @mixin \App\Models\Room
 */
class PublicPropertyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roomImageService = app(RoomImageService::class);
        $images = $this->whenLoaded('roomImages', fn () => $this->roomImages ?? collect(), collect());
        $primaryImage = $images->firstWhere('is_primary', true) ?? $images->first();
        $galleryImages = $images
            ->sortBy('sort_order')
            ->map(fn ($image) => $roomImageService->resolveImageUrl($image->image_path))
            ->filter()
            ->values()
            ->all();

        $purpose = $this->resolvePurpose(
            $request->input('purpose', $request->query('purpose'))
        );
        $approvedSaleContract = $this->whenLoaded('contracts', function () {
            return $this->contracts
                ->where('type', 'sale')
                ->where('status', 'active')
                ->sortByDesc('id')
                ->first();
        });

        $salePrice = $purpose === 'sale'
            ? $this->positiveDecimal($approvedSaleContract?->contract_total ?? $this->sale_price)
            : null;
        $monthlyRent = $purpose === 'rent'
            ? $this->positiveDecimal($this->rent_price)
            : null;
        $rentDeposit = $purpose === 'rent'
            ? $this->positiveDecimal($this->rent_deposit_price)
            : null;

        $buildingName = trim((string) ($this->building?->building_name ?? ''));
        $roomNumber = trim((string) ($this->room_number ?? ''));
        $propertyName = trim($buildingName.' '.$roomNumber);

        $payload = [
            'id' => $this->id,
            'property_name' => $propertyName !== '' ? $propertyName : null,
            'township' => $this->resolveTownship(),
            'city' => $this->resolveCity(),
            'address' => $this->nonEmptyString($this->building?->location),
            'status' => $this->nonEmptyString($this->status),
            'floor_number' => $this->floor_number !== null ? (int) $this->floor_number : null,
            'area_sqft' => $this->positiveDecimal($this->area_sqft),
            'width_ft' => $this->positiveDecimal($this->width_ft),
            'length_ft' => $this->positiveDecimal($this->length_ft),
            'purpose' => $purpose,
            'sale_price' => $salePrice,
            'monthly_rent' => $monthlyRent,
            'rent_price' => $monthlyRent,
            'rent_deposit_price' => $rentDeposit,
            'featured_image' => $primaryImage
                ? $roomImageService->resolveImageUrl($primaryImage->image_path)
                : null,
            'gallery_images' => $galleryImages,
            'description' => $this->nonEmptyString($this->description),
        ];

        return array_filter(
            $payload,
            static fn (mixed $value): bool => $value !== null && $value !== []
        );
    }

    private function resolvePurpose(mixed $requestedPurpose): string
    {
        if ($requestedPurpose === 'rent' || $requestedPurpose === 'sale') {
            return $requestedPurpose;
        }

        if (in_array($this->type, ['rent', 'sale'], true)) {
            return $this->type;
        }

        if ($this->type === 'both') {
            return 'sale';
        }

        return 'sale';
    }

    private function resolveTownship(): ?string
    {
        $location = $this->nonEmptyString($this->building?->location);

        if (! $location) {
            return null;
        }

        if (preg_match('/([^,]+)\s+Township/i', $location, $matches)) {
            return trim($matches[1]);
        }

        $first = trim(explode(',', $location)[0] ?? '');

        return $first !== '' ? $first : null;
    }

    private function resolveCity(): ?string
    {
        $location = $this->nonEmptyString($this->building?->location);

        if (! $location) {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $location))));

        if ($parts === []) {
            return null;
        }

        $city = end($parts) ?: null;

        return $this->nonEmptyString($city);
    }

    private function positiveDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) $value;

        return $number > 0 ? $number : null;
    }

    private function nonEmptyString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }
}
