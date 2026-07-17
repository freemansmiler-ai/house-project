<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FaqController extends Controller
{
    /**
     * Display a listing of FAQs.
     */
    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $query = Faq::query();

        if ($category) {
            $query->where('category', $category);
        }

        $faqs = $query->orderBy('category')->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $faqs
        ]);
    }

    /**
     * Store a newly created FAQ in storage (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'category' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $faq = Faq::create([
            'question' => $request->question,
            'answer' => $request->answer,
            'category' => $request->category ?? 'general',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'FAQ created successfully.',
            'data' => $faq
        ], 201); // 201 Created
    }

    /**
     * Update the specified FAQ in storage (Admin only).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $faq = Faq::find($id);

        if (!$faq) {
            return response()->json([
                'success' => false,
                'message' => 'FAQ not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'question' => 'sometimes|required|string|max:255',
            'answer' => 'sometimes|required|string',
            'category' => 'sometimes|required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $faq->update($request->only(['question', 'answer', 'category']));

        return response()->json([
            'success' => true,
            'message' => 'FAQ updated successfully.',
            'data' => $faq
        ]);
    }

    /**
     * Remove the specified FAQ from storage (Admin only).
     */
    public function destroy($id): JsonResponse
    {
        $faq = Faq::find($id);

        if (!$faq) {
            return response()->json([
                'success' => false,
                'message' => 'FAQ not found.'
            ], 404);
        }

        $faq->delete();

        return response()->json([
            'success' => true,
            'message' => 'FAQ deleted successfully.'
        ]);
    }
}
