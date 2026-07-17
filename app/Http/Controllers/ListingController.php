<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Listing::query();

        // Search query
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('state', 'like', "%{$search}%");
            });
        }

        // Filter by status (sale / rent)
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by type (house, apartment, villa, condo, townhouse)
        if ($request->has('type') && !empty($request->type) && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Filter by bedrooms
        if ($request->has('bedrooms') && !empty($request->bedrooms) && $request->bedrooms !== 'all') {
            if ($request->bedrooms === '4+') {
                $query->where('bedrooms', '>=', 4);
            } else {
                $query->where('bedrooms', intval($request->bedrooms));
            }
        }

        // Filter by bathrooms
        if ($request->has('bathrooms') && !empty($request->bathrooms) && $request->bathrooms !== 'all') {
            if ($request->bathrooms === '4+') {
                $query->where('bathrooms', '>=', 4);
            } else {
                $query->where('bathrooms', intval($request->bathrooms));
            }
        }

        // Filter by price range
        if ($request->has('min_price') && !empty($request->min_price)) {
            $query->where('price', '>=', floatval($request->min_price));
        }

        if ($request->has('max_price') && !empty($request->max_price)) {
            $query->where('price', '<=', floatval($request->max_price));
        }

        // Filter by featured
        if ($request->has('featured')) {
            $query->where('featured', filter_var($request->featured, FILTER_VALIDATE_BOOLEAN));
        }

        // Ordering
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        // Whitelist sorting columns
        if (in_array($sortBy, ['created_at', 'price', 'area'])) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $listings = $query->get();

        return response()->json([
            'success' => true,
            'count' => $listings->count(),
            'data' => $listings
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        $listing = Listing::find($id);

        if (!$listing) {
            return response()->json([
                'success' => false,
                'message' => 'Listing not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $listing
        ]);
    }
}
