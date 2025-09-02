<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileUploadService
{
    public function uploadImage(UploadedFile $file, string $directory = 'products'): array
    {
        try {
            $filename = $this->generateUniqueFilename($file);
            $path = $directory . '/' . $filename;

            // Always use local storage for now to ensure images are accessible
            return $this->uploadToLocal($file, $path);
        } catch (\Exception $e) {
            Log::error('File upload failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function uploadImageFromUrl(string $imageUrl, string $directory = 'products'): array
    {
        try {
            $filename = $this->generateUniqueFilenameFromUrl($imageUrl);
            $path = $directory . '/' . $filename;

            // Always use local storage for now to ensure images are accessible
            return $this->uploadUrlToLocal($imageUrl, $path);
        } catch (\Exception $e) {
            Log::error('URL image upload failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function shouldUseS3(): bool
    {
        // Temporarily disable S3 to ensure all uploads go to local storage
        return false;
        
        // Original logic (commented out for now):
        // return config('filesystems.default') === 's3' && 
        //        config('filesystems.disks.s3.key') && 
        //        config('filesystems.disks.s3.secret');
    }

    private function uploadToS3(UploadedFile $file, string $path): array
    {
        $disk = Storage::disk('s3');
        $disk->put($path, file_get_contents($file), 'public');

        return [
            'path' => $path,
            'url' => config('app.url') . '/storage/' . $path,
            'storage' => 's3'
        ];
    }

    private function uploadToLocal(UploadedFile $file, string $path): array
    {
        $disk = Storage::disk('public');
        $disk->put($path, file_get_contents($file));

        return [
            'path' => $path,
            'url' => config('app.url') . '/storage/' . $path,
            'storage' => 'local'
        ];
    }

    private function uploadUrlToS3(string $imageUrl, string $path): array
    {
        $disk = Storage::disk('s3');
        $imageContent = file_get_contents($imageUrl);
        $disk->put($path, $imageContent, 'public');

        return [
            'path' => $path,
            'url' => config('app.url') . '/storage/' . $path,
            'storage' => 's3'
        ];
    }

    private function uploadUrlToLocal(string $imageUrl, string $path): array
    {
        $disk = Storage::disk('public');
        
        try {
            $imageContent = file_get_contents($imageUrl);
            if ($imageContent === false) {
                throw new \Exception("Failed to download image from URL: {$imageUrl}");
            }
            
            $disk->put($path, $imageContent);
        } catch (\Exception $e) {
            Log::warning("Failed to download image from URL, using placeholder", [
                'url' => $imageUrl,
                'error' => $e->getMessage()
            ]);
            
            // If we can't download the image, create a placeholder
            $path = $this->createPlaceholderImage($path);
        }

        return [
            'path' => $path,
            'url' => config('app.url') . '/storage/' . $path,
            'storage' => 'local'
        ];
    }

    private function createPlaceholderImage(string $originalPath): string
    {
        // Create a simple placeholder image path
        $extension = pathinfo($originalPath, PATHINFO_EXTENSION) ?: 'png';
        $placeholderPath = 'products/placeholder_' . time() . '_' . Str::random(8) . '.' . $extension;
        
        // For now, we'll use a placeholder service URL
        // In production, you might want to generate an actual placeholder image
        $placeholderUrl = "https://via.placeholder.com/400x400/6B7280/FFFFFF?text=Image+Unavailable";
        
        try {
            $imageContent = file_get_contents($placeholderUrl);
            if ($imageContent !== false) {
                Storage::disk('public')->put($placeholderPath, $imageContent);
                return $placeholderPath;
            }
        } catch (\Exception $e) {
            Log::error("Failed to create placeholder image", ['error' => $e->getMessage()]);
        }
        
        return $originalPath;
    }

    private function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        return $name . '_' . time() . '_' . Str::random(8) . '.' . $extension;
    }

    private function generateUniqueFilenameFromUrl(string $url): string
    {
        $extension = pathinfo($url, PATHINFO_EXTENSION) ?: 'jpg';
        $name = Str::slug(pathinfo($url, PATHINFO_FILENAME));
        return $name . '_' . time() . '_' . Str::random(8) . '.' . $extension;
    }
}
