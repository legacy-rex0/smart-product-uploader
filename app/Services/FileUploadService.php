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

            if ($this->shouldUseS3()) {
                return $this->uploadToS3($file, $path);
            } else {
                return $this->uploadToLocal($file, $path);
            }
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

            if ($this->shouldUseS3()) {
                return $this->uploadUrlToS3($imageUrl, $path);
            } else {
                return $this->uploadUrlToLocal($imageUrl, $path);
            }
        } catch (\Exception $e) {
            Log::error('URL image upload failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function shouldUseS3(): bool
    {
        return config('filesystems.default') === 's3' && 
               config('filesystems.disks.s3.key') && 
               config('filesystems.disks.s3.secret');
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
        $imageContent = file_get_contents($imageUrl);
        $disk->put($path, $imageContent);

        return [
            'path' => $path,
            'url' => config('app.url') . '/storage/' . $path,
            'storage' => 'local'
        ];
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
