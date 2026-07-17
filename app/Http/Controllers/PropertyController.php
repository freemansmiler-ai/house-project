<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyController extends Controller
{
    /**
     * Fetch properties with dynamic filters and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Property::with(['user.agent', 'images', 'amenities']);

        // Only show active listings
        $query->where('status', 'active');

        // Search query (keyword match on title, description, location)
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('region', 'like', "%{$search}%");
            });
        }

        // Filter by city
        if ($request->has('city') && !empty($request->city) && $request->city !== 'all') {
            $query->where('city', 'like', "%{$request->city}%");
        }

        // Filter by region
        if ($request->has('region') && !empty($request->region) && $request->region !== 'all') {
            $query->where('region', 'like', "%{$request->region}%");
        }

        // Filter by category (residential, commercial, land, industrial)
        if ($request->has('category') && !empty($request->category) && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        // Filter by type (apartment, house, townhouse, condo, office, retail, warehouse, land)
        if ($request->has('type') && !empty($request->type) && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Filter by deal type (sale, rent)
        if ($request->has('deal_type') && !empty($request->deal_type) && $request->deal_type !== 'all') {
            $query->where('deal_type', $request->deal_type);
        }

        // Bedrooms
        if ($request->has('bedrooms') && !empty($request->bedrooms) && $request->bedrooms !== 'all') {
            if ($request->bedrooms === '4+') {
                $query->where('bedrooms', '>=', 4);
            } else {
                $query->where('bedrooms', intval($request->bedrooms));
            }
        }

        // Bathrooms
        if ($request->has('bathrooms') && !empty($request->bathrooms) && $request->bathrooms !== 'all') {
            if ($request->bathrooms === '4+') {
                $query->where('bathrooms', '>=', 4);
            } else {
                $query->where('bathrooms', intval($request->bathrooms));
            }
        }

        // Price constraints
        if ($request->has('min_price') && !empty($request->min_price)) {
            $query->where('price', '>=', floatval($request->min_price));
        }
        if ($request->has('max_price') && !empty($request->max_price)) {
            $query->where('price', '<=', floatval($request->max_price));
        }

        // Filter by amenities (Many-to-Many query)
        if ($request->has('amenities') && !empty($request->amenities)) {
            $amenityIds = is_array($request->amenities)
                ? $request->amenities
                : explode(',', $request->amenities);

            foreach ($amenityIds as $amenityId) {
                if (!empty($amenityId)) {
                    $query->whereHas('amenities', function ($q) use ($amenityId) {
                        $q->where('amenities.id', intval($amenityId));
                    });
                }
            }
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        if (in_array($sortBy, ['price', 'area', 'created_at', 'view_count'])) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $properties = $query->get();

        return response()->json([
            'success' => true,
            'count' => $properties->count(),
            'data' => $properties
        ]);
    }

    /**
     * Get featured properties.
     */
    public function featured(): JsonResponse
    {
        $properties = Property::with(['user.agent', 'images', 'amenities'])
            ->where('status', 'active')
            ->where('is_featured', true)
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $properties
        ]);
    }

    /**
     * Get latest properties.
     */
    public function latest(): JsonResponse
    {
        $properties = Property::with(['user.agent', 'images', 'amenities'])
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $properties
        ]);
    }

    /**
     * Retrieve details of a single property.
     */
    public function show(int $id): JsonResponse
    {
        $property = Property::with(['user.agent', 'user.landlord', 'user.profile', 'images', 'amenities', 'reviews.user'])
            ->where('status', 'active')
            ->find($id);

        if (!$property) {
            return response()->json([
                'success' => false,
                'message' => 'Property not found'
            ], 404);
        }

        // Increment views
        $property->increment('view_count');

        return response()->json([
            'success' => true,
            'data' => $property
        ]);
    }

    /**
     * Create a new property listing (verified landlords and agents only).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['landlord', 'agent'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only landlords and agents can publish listings.'
            ], 403);
        }

        $isVerified = false;
        if ($user->role === 'landlord' && $user->landlord && $user->landlord->is_verified) {
            $isVerified = true;
        } elseif ($user->role === 'agent' && $user->agent && $user->agent->is_verified) {
            $isVerified = true;
        }

        if (!$isVerified) {
            return response()->json([
                'success' => false,
                'message' => 'Your landlord/agent account must be verified by admin before listing properties.'
            ], 403);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|in:GHS,USD',
            'period' => 'nullable|string|in:day,week,month,year',
            'category' => 'required|string|in:residential,commercial,land,industrial',
            'type' => 'required|string|in:apartment,house,townhouse,condo,office,retail,warehouse,land',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|numeric|min:0',
            'area' => 'nullable|numeric|min:0',
            'location' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'region' => 'required|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'video_url' => 'nullable|url',
            'amenities' => 'nullable|array',
            'amenities.*' => 'exists:amenities,id',
            'images' => 'nullable|array',
            'images.*' => 'string|url',
            'ownership_document_path' => 'nullable|string|max:500',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Subscription listing & featured limits check
        $activeSub = \App\Models\Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->first();

        $propertyLimit = 1; // Default Free plan limit
        $featuredLimit = 0; // Default Free plan limit
        $planName = 'Free';
        if ($activeSub) {
            $propertyLimit = $activeSub->property_limit;
            $featuredLimit = $activeSub->featured_limit;
            $planName = $activeSub->plan_name;
        }

        $currentPropertiesCount = Property::where('user_id', $user->id)->count();

        if ($currentPropertiesCount >= $propertyLimit) {
            return response()->json([
                'success' => false,
                'message' => "You have reached the maximum listing limit for your {$planName} plan (limit: {$propertyLimit}). Please upgrade your subscription to publish more listings."
            ], 403);
        }

        if ($request->boolean('is_featured')) {
            $currentFeaturedCount = Property::where('user_id', $user->id)->where('is_featured', true)->count();
            if ($currentFeaturedCount >= $featuredLimit) {
                return response()->json([
                    'success' => false,
                    'message' => "You have reached the maximum featured property limit for your {$planName} plan (limit: {$featuredLimit}). Please upgrade your subscription."
                ], 403);
            }
        }

        $slug = \Illuminate\Support\Str::slug($request->title) . '-' . time();

        $property = Property::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'slug' => $slug,
            'description' => $request->description,
            'price' => $request->price,
            'currency' => $request->currency ?? 'GHS',
            'period' => $request->period,
            'category' => $request->category,
            'type' => $request->type,
            'bedrooms' => $request->bedrooms,
            'bathrooms' => $request->bathrooms,
            'area' => $request->area,
            'location' => $request->location,
            'city' => $request->city,
            'region' => $request->region,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'video_url' => $request->video_url,
            'status' => $request->input('status', 'pending_approval'),
            'ownership_document_path' => $request->ownership_document_path,
            'is_featured' => $request->boolean('is_featured'),
            'published_at' => null,
        ]);

        if ($request->has('amenities')) {
            $property->amenities()->sync($request->amenities);
        }

        if ($request->has('images') && !empty($request->images)) {
            foreach ($request->images as $index => $imageUrl) {
                \App\Models\PropertyImage::create([
                    'property_id' => $property->id,
                    'image_path' => $imageUrl,
                    'is_thumbnail' => $index === 0,
                    'sort_order' => $index,
                ]);
            }
        } else {
            \App\Models\PropertyImage::create([
                'property_id' => $property->id,
                'image_path' => 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=1200&q=80',
                'is_thumbnail' => true,
                'sort_order' => 0,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Listing published successfully!',
            'data' => $property->load(['images', 'amenities'])
        ], 201);
    }

    /**
     * Update an existing property listing (verified owner only).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $property = Property::find($id);

        if (!$property) {
            return response()->json([
                'success' => false,
                'message' => 'Property not found.'
            ], 404);
        }

        if ($property->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You do not own this listing.'
            ], 403);
        }

        if (!in_array($user->role, ['landlord', 'agent']) && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only landlords and agents can manage listings.'
            ], 403);
        }

        $isVerified = false;
        if ($user->role === 'admin') {
            $isVerified = true;
        } elseif ($user->role === 'landlord' && $user->landlord && $user->landlord->is_verified) {
            $isVerified = true;
        } elseif ($user->role === 'agent' && $user->agent && $user->agent->is_verified) {
            $isVerified = true;
        }

        if (!$isVerified) {
            return response()->json([
                'success' => false,
                'message' => 'Your landlord/agent account must be verified by admin.'
            ], 403);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'price' => 'sometimes|required|numeric|min:0',
            'currency' => 'nullable|string|in:GHS,USD',
            'period' => 'nullable|string|in:day,week,month,year',
            'category' => 'sometimes|required|string|in:residential,commercial,land,industrial',
            'type' => 'sometimes|required|string|in:apartment,house,townhouse,condo,office,retail,warehouse,land',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|numeric|min:0',
            'area' => 'nullable|numeric|min:0',
            'location' => 'sometimes|required|string|max:255',
            'city' => 'sometimes|required|string|max:100',
            'region' => 'sometimes|required|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'status' => 'sometimes|required|string|in:active,inactive,pending,pending_approval,sold,rented,rejected,changes_requested',
            'ownership_document_path' => 'nullable|string|max:500',
            'video_url' => 'nullable|url',
            'amenities' => 'nullable|array',
            'amenities.*' => 'exists:amenities,id',
            'images' => 'nullable|array',
            'images.*' => 'string|url',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check featured limit if request wants to feature the property
        if ($request->has('is_featured') && $request->boolean('is_featured')) {
            if (!$property->is_featured) {
                $activeSub = \App\Models\Subscription::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('ends_at', '>', now())
                    ->first();

                $featuredLimit = 0; // Default Free plan limit
                $planName = 'Free';
                if ($activeSub) {
                    $featuredLimit = $activeSub->featured_limit;
                    $planName = $activeSub->plan_name;
                }

                $currentFeaturedCount = Property::where('user_id', $user->id)->where('is_featured', true)->count();
                if ($currentFeaturedCount >= $featuredLimit) {
                    return response()->json([
                        'success' => false,
                        'message' => "You have reached the maximum featured property limit for your {$planName} plan (limit: {$featuredLimit}). Please upgrade your subscription."
                    ], 403);
                }
            }
        }

        $property->fill($request->only([
            'title', 'description', 'price', 'currency', 'period', 'category', 'type',
            'bedrooms', 'bathrooms', 'area', 'location', 'city', 'region',
            'latitude', 'longitude', 'status', 'video_url', 'ownership_document_path', 'is_featured'
        ]));

        if ($request->has('title')) {
            $property->slug = \Illuminate\Support\Str::slug($request->title) . '-' . time();
        }

        // Reset status to pending_approval if updated by landlord/agent
        if ($user->role !== 'admin') {
            $property->status = 'pending_approval';
        }

        $property->save();

        if ($request->has('amenities')) {
            $property->amenities()->sync($request->amenities);
        }

        if ($request->has('images') && !empty($request->images)) {
            \App\Models\PropertyImage::where('property_id', $property->id)->delete();

            foreach ($request->images as $index => $imageUrl) {
                \App\Models\PropertyImage::create([
                    'property_id' => $property->id,
                    'image_path' => $imageUrl,
                    'is_thumbnail' => $index === 0,
                    'sort_order' => $index,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Listing updated successfully!',
            'data' => $property->load(['images', 'amenities'])
        ]);
    }

    /**
     * Delete an existing property listing (owner only).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $property = Property::find($id);

        if (!$property) {
            return response()->json([
                'success' => false,
                'message' => 'Property not found.'
            ], 404);
        }

        if ($property->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You do not own this listing.'
            ], 403);
        }

        \App\Models\PropertyImage::where('property_id', $property->id)->delete();
        $property->amenities()->detach();
        $property->delete();

        return response()->json([
            'success' => true,
            'message' => 'Property listing deleted successfully!'
        ]);
    }

    /**
     * Retrieve popular cities with count of active properties.
     */
    public function popularCities(): JsonResponse
    {
        // Fetch count of properties grouped by city
        $cities = Property::select('city', DB::raw('count(*) as total'))
            ->where('status', 'active')
            ->groupBy('city')
            ->orderBy('total', 'desc')
            ->limit(4)
            ->get();

        // Map with high-quality unsplash imagery matching standard Ghanaian cities
        $cityImages = [
            'Accra' => 'https://images.unsplash.com/photo-1596176530529-78163a4f7af2?auto=format&fit=crop&w=600&q=80',
            'Kumasi' => 'https://images.unsplash.com/photo-1580618672591-eb180b1a973f?auto=format&fit=crop&w=600&q=80',
            'Tema' => 'https://images.unsplash.com/photo-1569336415962-a4bd9f69cd83?auto=format&fit=crop&w=600&q=80',
            'Takoradi' => 'https://images.unsplash.com/photo-1504280390367-361c6d9f38f4?auto=format&fit=crop&w=600&q=80',
        ];

        $data = $cities->map(function ($cityItem) use ($cityImages) {
            $cityName = $cityItem->city;
            return [
                'name' => $cityName,
                'count' => $cityItem->total,
                'image_url' => $cityImages[$cityName] ?? 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=600&q=80'
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Retrieve verified agents list.
     */
    public function verifiedAgents(): JsonResponse
    {
        $agents = User::with(['profile', 'agent'])
            ->where('role', 'agent')
            ->whereHas('agent', function ($q) {
                $q->where('is_verified', true);
            })
            ->limit(4)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $agents
        ]);
    }

    /**
     * Retrieve all available amenities.
     */
    public function allAmenities(): JsonResponse
    {
        $amenities = \App\Models\Amenity::all();
        return response()->json([
            'success' => true,
            'data' => $amenities
        ]);
    }
}
