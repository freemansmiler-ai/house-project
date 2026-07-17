<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /**
     * Upload multiple property images.
     * Compresses files, generates cropped thumbnails, and uploads to Cloudinary if configured.
     */
    public function uploadImages(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'images' => 'required|array|min:1',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,webp,gif|max:10240', // max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $uploadedUrls = [];
        $thumbnailUrls = [];

        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $uploadPreset = env('CLOUDINARY_UPLOAD_PRESET', 'propertyhub_unsigned');

        foreach ($request->file('images') as $file) {
            $uuid = Str::uuid()->toString();
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeName = Str::slug($originalName) . '-' . $uuid;

            // Define local temp paths
            $tempDir = storage_path('app/temp_uploads/');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            $compressedLocalPath = $tempDir . $safeName . '.jpg';
            $thumbnailLocalPath = $tempDir . 'thumb-' . $safeName . '.jpg';

            // 1. COMPRESS Main Image (GD Library)
            $compressedSuccess = $this->compressAndSaveImage($file, $compressedLocalPath, 75, 1200);

            // 2. GENERATE Thumbnail (GD Library, cropped square 300x300)
            $thumbnailSuccess = $this->generateThumbnail($file, $thumbnailLocalPath, 65, 300);

            if (!$compressedSuccess || !$thumbnailSuccess) {
                Log::error("Failed to process images via GD library.");
                return response()->json([
                    'success' => false,
                    'message' => 'Image processing failed. Verify GD configuration.'
                ], 500);
            }

            $mainUrl = '';
            $thumbUrl = '';

            // 3. CLOUDINARY INTEGRATION (Fallback to Local storage if keys are absent)
            if (!empty($cloudName)) {
                try {
                    // Upload main image
                    $mainCloudinaryResponse = Http::attach(
                        'file',
                        file_get_contents($compressedLocalPath),
                        $safeName . '.jpg'
                    )->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                        'upload_preset' => $uploadPreset,
                        'public_id' => 'properties/' . $safeName,
                    ]);

                    // Upload thumbnail
                    $thumbCloudinaryResponse = Http::attach(
                        'file',
                        file_get_contents($thumbnailLocalPath),
                        'thumb-' . $safeName . '.jpg'
                    )->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                        'upload_preset' => $uploadPreset,
                        'public_id' => 'properties/thumbs/thumb-' . $safeName,
                    ]);

                    if ($mainCloudinaryResponse->successful() && $thumbCloudinaryResponse->successful()) {
                        $mainUrl = $mainCloudinaryResponse->json()['secure_url'];
                        $thumbUrl = $thumbCloudinaryResponse->json()['secure_url'];
                    } else {
                        Log::warning("Cloudinary upload failed: " . $mainCloudinaryResponse->body() . ". Falling back to local storage.");
                    }
                } catch (\Exception $e) {
                    Log::error("Cloudinary connection exception: " . $e->getMessage() . ". Falling back to local storage.");
                }
            }

            // Fallback to local disk if Cloudinary was not used or failed
            if (empty($mainUrl)) {
                // Move from temp to public storage disk
                $publicFolder = 'properties/' . date('Y/m');
                Storage::disk('public')->put($publicFolder . '/' . $safeName . '.jpg', file_get_contents($compressedLocalPath));
                Storage::disk('public')->put($publicFolder . '/thumb-' . $safeName . '.jpg', file_get_contents($thumbnailLocalPath));

                $mainUrl = asset('storage/' . $publicFolder . '/' . $safeName . '.jpg');
                $thumbUrl = asset('storage/' . $publicFolder . '/thumb-' . $safeName . '.jpg');
            }

            // Cleanup temp local files
            if (file_exists($compressedLocalPath)) unlink($compressedLocalPath);
            if (file_exists($thumbnailLocalPath)) unlink($thumbnailLocalPath);

            $uploadedUrls[] = $mainUrl;
            $thumbnailUrls[] = $thumbUrl;
        }

        return response()->json([
            'success' => true,
            'message' => 'Images uploaded and processed successfully!',
            'images' => $uploadedUrls,
            'thumbnails' => $thumbnailUrls,
            'provider' => !empty($cloudName) ? 'cloudinary' : 'local'
        ]);
    }

    /**
     * Compress and scale image.
     */
    private function compressAndSaveImage($file, $destinationPath, $quality, $maxWidth): bool
    {
        list($width, $height, $type) = getimagesize($file->getRealPath());

        switch ($type) {
            case IMAGETYPE_JPEG:
                $srcImage = imagecreatefromjpeg($file->getRealPath());
                break;
            case IMAGETYPE_PNG:
                $srcImage = imagecreatefrompng($file->getRealPath());
                imagealphablending($srcImage, true);
                imagesavealpha($srcImage, true);
                break;
            case IMAGETYPE_GIF:
                $srcImage = imagecreatefromgif($file->getRealPath());
                break;
            case IMAGETYPE_WEBP:
                $srcImage = imagecreatefromwebp($file->getRealPath());
                break;
            default:
                return false;
        }

        if (!$srcImage) {
            return false;
        }

        $newWidth = $width;
        $newHeight = $height;
        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = intval(($height / $width) * $maxWidth);
        }

        $dstImage = imagecreatetruecolor($newWidth, $newHeight);

        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        if (!file_exists(dirname($destinationPath))) {
            mkdir(dirname($destinationPath), 0777, true);
        }

        $success = imagejpeg($dstImage, $destinationPath, $quality);

        imagedestroy($srcImage);
        imagedestroy($dstImage);

        return $success;
    }

    /**
     * Crop and resize image to square aspect ratio thumbnail.
     */
    private function generateThumbnail($file, $destinationPath, $quality, $thumbSize): bool
    {
        list($width, $height, $type) = getimagesize($file->getRealPath());

        switch ($type) {
            case IMAGETYPE_JPEG:
                $srcImage = imagecreatefromjpeg($file->getRealPath());
                break;
            case IMAGETYPE_PNG:
                $srcImage = imagecreatefrompng($file->getRealPath());
                break;
            case IMAGETYPE_GIF:
                $srcImage = imagecreatefromgif($file->getRealPath());
                break;
            case IMAGETYPE_WEBP:
                $srcImage = imagecreatefromwebp($file->getRealPath());
                break;
            default:
                return false;
        }

        if (!$srcImage) {
            return false;
        }

        $dstImage = imagecreatetruecolor($thumbSize, $thumbSize);

        $srcRatio = $width / $height;
        $dstRatio = 1.0; // square

        $srcX = 0;
        $srcY = 0;
        $srcW = $width;
        $srcH = $height;

        if ($srcRatio > $dstRatio) {
            $srcW = intval($height * $dstRatio);
            $srcX = intval(($width - $srcW) / 2);
        } else {
            $srcH = intval($width / $dstRatio);
            $srcY = intval(($height - $srcH) / 2);
        }

        imagecopyresampled($dstImage, $srcImage, 0, 0, $srcX, $srcY, $thumbSize, $thumbSize, $srcW, $srcH);

        if (!file_exists(dirname($destinationPath))) {
            mkdir(dirname($destinationPath), 0777, true);
        }

        $success = imagejpeg($dstImage, $destinationPath, $quality);

        imagedestroy($srcImage);
        imagedestroy($dstImage);

        return $success;
    }
}
