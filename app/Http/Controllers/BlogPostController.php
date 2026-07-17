<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\BlogCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BlogPostController extends Controller
{
    /**
     * Display a listing of published posts. (Public API)
     */
    public function index(Request $request): JsonResponse
    {
        $query = BlogPost::with(['author:id,name,avatar', 'category:id,name,slug'])
            ->where('status', 'published')
            ->orderBy('published_at', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by category slug
        if ($request->has('category')) {
            $categorySlug = $request->input('category');
            $query->whereHas('category', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
        }

        // Search query
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $posts = $query->paginate($request->input('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $posts
        ]);
    }

    /**
     * Display a listing of all posts for admin management. (Admin API)
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = BlogPost::with(['author:id,name', 'category:id,name'])
            ->orderBy('created_at', 'desc');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $posts = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $posts
        ]);
    }

    /**
     * Display a single post by slug. (Public API)
     */
    public function show($slug): JsonResponse
    {
        $post = BlogPost::with(['author:id,name,avatar', 'category:id,name,slug'])
            ->where('slug', $slug)
            ->first();

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Blog post not found.'
            ], 404);
        }

        // Return post (if draft, only author/admin should see it, but for simplicity let's allow viewing published,
        // and if it's draft, we check if user is admin)
        if ($post->status === 'draft') {
            $user = auth('sanctum')->user();
            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'This blog post is currently a draft.'
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $post
        ]);
    }

    /**
     * Store a newly created post. (Admin API)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'blog_category_id' => 'nullable|exists:blog_categories,id',
            'content' => 'required|string',
            'featured_image' => 'nullable|string|max:2048', // URL string
            'status' => 'required|in:draft,published',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'seo_keywords' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $slug = Str::slug($request->title);
        // Ensure unique slug
        $originalSlug = $slug;
        $count = 1;
        while (BlogPost::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        // SEO fallbacks
        $seoTitle = $request->input('seo_title') ?: $request->title;
        
        $seoDesc = $request->input('seo_description');
        if (!$seoDesc) {
            // Strip HTML and truncate content
            $cleanContent = strip_tags($request->content);
            $seoDesc = Str::limit($cleanContent, 160);
        }

        $publishedAt = null;
        if ($request->status === 'published') {
            $publishedAt = now();
        }

        $post = BlogPost::create([
            'user_id' => $request->user()->id,
            'blog_category_id' => $request->blog_category_id,
            'title' => $request->title,
            'slug' => $slug,
            'content' => $request->content,
            'featured_image' => $request->featured_image,
            'status' => $request->status,
            'seo_title' => $seoTitle,
            'seo_description' => $seoDesc,
            'seo_keywords' => $request->seo_keywords,
            'published_at' => $publishedAt
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Blog post created successfully.',
            'data' => $post
        ], 201);
    }

    /**
     * Update the specified post. (Admin API)
     */
    public function update(Request $request, $id): JsonResponse
    {
        $post = BlogPost::find($id);

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Blog post not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'blog_category_id' => 'nullable|exists:blog_categories,id',
            'content' => 'required|string',
            'featured_image' => 'nullable|string|max:2048',
            'status' => 'required|in:draft,published',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'seo_keywords' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $slug = $post->slug;
        // Generate new slug only if title changes
        if ($post->title !== $request->title) {
            $slug = Str::slug($request->title);
            $originalSlug = $slug;
            $count = 1;
            while (BlogPost::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug = $originalSlug . '-' . $count;
                $count++;
            }
        }

        // SEO fallbacks
        $seoTitle = $request->input('seo_title') ?: $request->title;
        $seoDesc = $request->input('seo_description');
        if (!$seoDesc) {
            $cleanContent = strip_tags($request->content);
            $seoDesc = Str::limit($cleanContent, 160);
        }

        $publishedAt = $post->published_at;
        if ($request->status === 'published' && !$post->published_at) {
            $publishedAt = now();
        } elseif ($request->status === 'draft') {
            $publishedAt = null;
        }

        $post->update([
            'blog_category_id' => $request->blog_category_id,
            'title' => $request->title,
            'slug' => $slug,
            'content' => $request->content,
            'featured_image' => $request->featured_image,
            'status' => $request->status,
            'seo_title' => $seoTitle,
            'seo_description' => $seoDesc,
            'seo_keywords' => $request->seo_keywords,
            'published_at' => $publishedAt
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Blog post updated successfully.',
            'data' => $post
        ]);
    }

    /**
     * Remove the specified post from storage. (Admin API)
     */
    public function destroy($id): JsonResponse
    {
        $post = BlogPost::find($id);

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Blog post not found.'
            ], 404);
        }

        $post->delete();

        return response()->json([
            'success' => true,
            'message' => 'Blog post deleted successfully.'
        ]);
    }

    /**
     * Upload an image from the rich text editor. (Admin API)
     */
    public function uploadEditorImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('blog', 'public');
            $url = asset('storage/' . $path);

            return response()->json([
                'success' => true,
                'url' => $url
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No image uploaded.'
        ], 400);
    }
}
