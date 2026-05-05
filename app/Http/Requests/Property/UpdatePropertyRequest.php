<?php

namespace App\Http\Requests\Property;

use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $property = $this->route('property');

        return [
            'property_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('properties', 'property_code')->ignore($property?->id),
            ],
            'property_name' => ['required', 'string', 'max:255'],
            'property_type' => ['required', Rule::in([Property::TYPE_APARTMENT, Property::TYPE_CONDO, Property::TYPE_HOUSE])],
            'purpose' => ['required', Rule::in([Property::PURPOSE_SALE, Property::PURPOSE_RENT])],
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'building' => ['nullable', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:100'],
            'unit_number' => ['nullable', 'string', 'max:100'],
            'township' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'bedrooms' => ['nullable', 'integer', 'min:0'],
            'bathrooms' => ['nullable', 'integer', 'min:0'],
            'area_sqft' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in([
                Property::STATUS_AVAILABLE,
                Property::STATUS_RESERVED,
                Property::STATUS_OCCUPIED,
                Property::STATUS_SOLD,
            ])],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'monthly_rent' => ['nullable', 'numeric', 'min:0'],
            'maintenance_fee' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'featured_image' => ['nullable', 'url'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['string', 'url'],
            'is_featured' => ['nullable', 'boolean'],
            'listed_at' => ['nullable', 'date'],
        ];
    }
}
