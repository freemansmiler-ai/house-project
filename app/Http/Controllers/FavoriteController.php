<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FavoriteController extends Controller
{
    /**
     * Display a listing of the user's favorite properties.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $favorites = $user->savedProperties()
            ->with(['images', 'user'])
            ->where('status', 'active')
            ->orderBy('saved_properties.created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $favorites
        ]);
    }

    /**
     * Toggle saving/removing a property from favorites.
     */
    public function toggle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $propertyId = $request->property_id;

        // Check if already saved
        $isSaved = $user->savedProperties()->where('property_id', $propertyId)->exists();

        if ($isSaved) {
            // Remove from saved list
            $user->savedProperties()->detach($propertyId);
            $saved = false;
            $message = 'Property removed from favorites.';
        } else {
            // Save property
            $user->savedProperties()->attach($propertyId);
            $saved = true;
            $message = 'Property saved to favorites.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'is_saved' => $saved
        ]);
    }
}
