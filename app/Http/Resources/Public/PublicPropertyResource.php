<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Room
 */
class PublicPropertyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $images = $this->whenLoaded('roomImages', fn () => $this->roomImages ?? collect(), collect());
        $primaryImage = $images->firstWhere('is_primary', true) ?? $images->first();
        $galleryImages = $images
            ->sortBy('sort_order')
            ->pluck('image_path')
            ->filter()
            ->values()
            ->all();

        $approvedContract = $this->whenLoaded('contracts', function () {
            return $this->contracts
                ->where('type', 'sale')
                ->where('status', 'approved')
                ->sortByDesc('id')
                ->first();
        });

        $salePrice = $approvedContract?->contract_total ?? $this->sale_price;
        $propertyCode = $approvedContract?->contract_number ?? ('RR-S-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT));

        return [
            'id' => $this->id,
            'property_name' => trim(($this->building?->building_name ?? 'Rosewood Property').' '.$this->room_number),
            'property_code' => $propertyCode,
            'property_type' => $this->resolvePropertyType(),
            'township' => $this->resolveTownship(),
            'address' => $this->building?->location,
            'status' => $this->status,
            'bedrooms' => $this->resolveBedrooms(),
            'bathrooms' => $this->resolveBathrooms(),
            'area_sqft' => $this->area_sqft,
            'width_ft' => $this->width_ft,
            'length_ft' => $this->length_ft,
            'purpose' => 'sale',
            'sale_price' => $salePrice,
            'featured_image' => $primaryImage?->image_path,
            'gallery_images' => $galleryImages,
            'description' => $this->description,
        ];
    }

    private function resolvePropertyType(): string
    {
        $area = (float) $this->area_sqft;

        return match (true) {
            $area >= 3000 => 'villa',
            $area >= 2000 => 'house',
            $area >= 1500 => 'penthouse',
            default => 'condo',
        };
    }

    private function resolveTownship(): ?string
    {
        $location = $this->building?->location;

        if (! $location) {
            return null;
        }

        if (preg_match('/([^,]+)\s+Township/i', $location, $matches)) {
            return trim($matches[1]);
        }

        return trim(explode(',', $location)[0] ?? $location);
    }

    private function resolveBedrooms(): int
    {
        $area = (float) $this->area_sqft;

        return max(1, (int) round($area / 400));
    }

    private function resolveBathrooms(): int
    {
        return max(1, (int) ceil($this->resolveBedrooms() / 1.5));
    }
}
