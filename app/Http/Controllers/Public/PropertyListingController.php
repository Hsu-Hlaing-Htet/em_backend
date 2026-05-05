<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Property::query()->with('owner');

        if ($request->filled('purpose')) {
            $query->where('purpose', $request->string('purpose'));
        }

        if ($request->filled('property_type')) {
            $query->where('property_type', $request->string('property_type'));
        }

        if ($request->filled('township')) {
            $query->where('township', 'like', '%'.$request->string('township').'%');
        }

        if ($request->filled('bedrooms')) {
            $query->where('bedrooms', '>=', (int) $request->integer('bedrooms'));
        }

        if ($request->filled('property_id')) {
            $query->where('property_code', 'like', '%'.$request->string('property_id').'%');
        }

        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('property_name', 'like', "%{$term}%")
                    ->orWhere('property_code', 'like', "%{$term}%")
                    ->orWhere('address', 'like', "%{$term}%");
            });
        }

        if ($request->filled('budget_min')) {
            $min = (float) $request->input('budget_min');
            $query->where(function ($builder) use ($min): void {
                $builder->where('sale_price', '>=', $min)->orWhere('monthly_rent', '>=', $min);
            });
        }

        if ($request->filled('budget_max')) {
            $max = (float) $request->input('budget_max');
            $query->where(function ($builder) use ($max): void {
                $builder->where('sale_price', '<=', $max)->orWhere('monthly_rent', '<=', $max);
            });
        }

        $properties = $query->latest('listed_at')->latest('id')->paginate(12);

        return response()->json($properties);
    }

    public function featured(): JsonResponse
    {
        $query = Property::query()->where('is_featured', true)->where('status', Property::STATUS_AVAILABLE);

        return response()->json([
            'sale' => (clone $query)->where('purpose', Property::PURPOSE_SALE)->limit(6)->get(),
            'rent' => (clone $query)->where('purpose', Property::PURPOSE_RENT)->limit(6)->get(),
            'houses' => (clone $query)->where('property_type', Property::TYPE_HOUSE)->limit(6)->get(),
            'condos' => (clone $query)->whereIn('property_type', [Property::TYPE_CONDO, Property::TYPE_APARTMENT])->limit(6)->get(),
        ]);
    }

    public function show(Property $property): JsonResponse
    {
        return response()->json($property->load(['owner', 'contracts']));
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'total_properties' => Property::count(),
            'total_clients' => Property::query()->whereNotNull('owner_user_id')->count(),
            'available' => Property::where('status', Property::STATUS_AVAILABLE)->count(),
            'occupied' => Property::where('status', Property::STATUS_OCCUPIED)->count(),
            'years_of_service' => 12,
        ]);
    }
}
