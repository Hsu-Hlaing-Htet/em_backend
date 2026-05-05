<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Property\StorePropertyRequest;
use App\Http\Requests\Property\UpdatePropertyRequest;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
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

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('property_name', 'like', "%{$term}%")
                    ->orWhere('property_code', 'like', "%{$term}%")
                    ->orWhere('township', 'like', "%{$term}%");
            });
        }

        return response()->json($query->latest('id')->paginate((int) $request->integer('per_page', 12)));
    }

    public function store(StorePropertyRequest $request): JsonResponse
    {
        $property = Property::create($request->validated());

        return response()->json([
            'message' => 'Property created successfully.',
            'data' => $property->load('owner'),
        ], 201);
    }

    public function show(Property $property): JsonResponse
    {
        return response()->json($property->load(['owner', 'contracts', 'invoices']));
    }

    public function update(UpdatePropertyRequest $request, Property $property): JsonResponse
    {
        $property->update($request->validated());

        return response()->json([
            'message' => 'Property updated successfully.',
            'data' => $property->fresh()->load('owner'),
        ]);
    }

    public function destroy(Property $property): JsonResponse
    {
        $property->delete();

        return response()->json([
            'message' => 'Property deleted successfully.',
        ]);
    }
}
