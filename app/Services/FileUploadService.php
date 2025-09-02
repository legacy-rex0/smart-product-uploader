<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileUploadService
{
    private bool $preferS3 = false;

    /**
     * Set preference for S3 storage when available
     */
    public function preferS3(bool $prefer = true): self
    {
        $this->preferS3 = $prefer;
        return $this;
    }

    /**
     * Get current S3 preference
     */
    public function getS3Preference(): bool
    {
        return $this->preferS3;
    }

    /**
     * Set persistent S3 preference that will be remembered across requests
     */
    public function setPersistentS3Preference(bool $prefer = true): self
    {
        // Store in config cache for persistence
        config(['app.prefer_s3' => $prefer]);
        
        // Also set instance preference
        $this->preferS3 = $prefer;
        
        return $this;
    }

    /**
     * Get persistent S3 preference
     */
    public function getPersistentS3Preference(): bool
    {
        return config('app.prefer_s3', false);
    }

    public function uploadImage(UploadedFile $file, string $directory = 'products'): array
    {
        try {
            $filename = $this->generateUniqueFilename($file);
            $path = $directory . '/' . $filename;

            Log::info('Starting file upload', [
                'filename' => $filename,
                'path' => $path,
                'should_use_s3' => $this->shouldUseS3(),
                's3_preference' => $this->preferS3,
                'env_prefer_s3' => env('PREFER_S3', false)
            ]);

            if ($this->shouldUseS3()) {
                Log::info('Using S3 for upload', ['path' => $path]);
                return $this->uploadToS3($file, $path);
            } else {
                Log::info('Using local storage for upload', ['path' => $path]);
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

            Log::info('Starting URL image upload', [
                'url' => $imageUrl,
                'filename' => $filename,
                'path' => $path,
                'should_use_s3' => $this->shouldUseS3(),
                's3_preference' => $this->preferS3,
                'env_prefer_s3' => env('PREFER_S3', false)
            ]);

            if ($this->shouldUseS3()) {
                Log::info('Using S3 for URL upload', ['path' => $path]);
                return $this->uploadUrlToS3($imageUrl, $path);
            } else {
                Log::info('Using local storage for URL upload', ['path' => $path]);
                return $this->uploadUrlToLocal($imageUrl, $path);
            }
        } catch (\Exception $e) {
            Log::error('URL image upload failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Force upload to S3 regardless of default configuration
     */
    public function forceS3Upload(UploadedFile $file, string $directory = 'products'): array
    {
        try {
            $filename = $this->generateUniqueFilename($file);
            $path = $directory . '/' . $filename;
            return $this->uploadToS3($file, $path);
        } catch (\Exception $e) {
            Log::error('Forced S3 upload failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Force URL upload to S3 regardless of default configuration
     */
    public function forceS3UrlUpload(string $imageUrl, string $directory = 'products'): array
    {
        try {
            $filename = $this->generateUniqueFilenameFromUrl($imageUrl);
            $path = $directory . '/' . $filename;
            return $this->uploadUrlToS3($imageUrl, $path);
        } catch (\Exception $e) {
            Log::error('Forced S3 URL upload failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Check if S3 should be used based on configuration
     */
    public function shouldUseS3(): bool
    {
        // Check if S3 is configured and credentials are available
        $s3Config = config('filesystems.disks.s3');
        
        // Check if we have the minimum required S3 configuration
        $hasCredentials = !empty($s3Config['key']) && 
                         !empty($s3Config['secret']) && 
                         !empty($s3Config['bucket']) && 
                         !empty($s3Config['region']);
        
        // Use S3 if it's the default disk OR if we have valid credentials and prefer S3
        return (config('filesystems.default') === 's3' && $hasCredentials) || 
               ($hasCredentials && ($this->preferS3 || config('app.prefer_s3', false) || env('PREFER_S3', false)));
    }

    private function uploadToS3(UploadedFile $file, string $path): array
    {
        try {
            $disk = Storage::disk('s3');
            
            // Upload file with public visibility
            $disk->put($path, file_get_contents($file), 'public');
            
            // Ensure the file is publicly accessible
            $disk->setVisibility($path, 'public');

            // Generate signed URL for S3 files
            $signedUrl = $this->generateSignedS3Url($path);
            
            return [
                'path' => $path,
                'url' => $signedUrl,
                'storage' => 's3',
                'full_path' => $path
            ];
        } catch (\Exception $e) {
            Log::warning('S3 upload failed, falling back to local storage', [
                'error' => $e->getMessage(),
                'path' => $path
            ]);
            
            // Fallback to local storage if S3 fails
            return $this->uploadToLocal($file, $path);
        }
    }

    private function uploadToLocal(UploadedFile $file, string $path): array
    {
        $disk = Storage::disk('public');
        $disk->put($path, file_get_contents($file));

        return [
            'path' => $path,
            'url' => config('app.url') . '/storage/' . $path,
            'storage' => 'local',
            'full_path' => $path
        ];
    }

    private function uploadUrlToS3(string $imageUrl, string $path): array
    {
        try {
            $disk = Storage::disk('s3');
            $imageContent = file_get_contents($imageUrl);
            
            if ($imageContent === false) {
                throw new \Exception("Failed to download image from URL: {$imageUrl}");
            }
            
            // Upload file with public visibility
            $disk->put($path, $imageContent, 'public');
            
            // Ensure the file is publicly accessible
            $disk->setVisibility($path, 'public');

            // Generate signed URL for S3 files
            $signedUrl = $this->generateSignedS3Url($path);
            
            return [
                'path' => $path,
                'url' => $signedUrl,
                'storage' => 's3',
                'full_path' => $path
            ];
        } catch (\Exception $e) {
            Log::warning('S3 URL upload failed, falling back to local storage', [
                'error' => $e->getMessage(),
                'url' => $imageUrl,
                'path' => $path
            ]);
            
            // Fallback to local storage if S3 fails
            return $this->uploadUrlToLocal($imageUrl, $path);
        }
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
            'storage' => 'local',
            'full_path' => $path
        ];
    }

    private function createPlaceholderImage(string $originalPath): string
    {
        // Create a simple placeholder image path
        $extension = pathinfo($originalPath, PATHINFO_EXTENSION) ?: 'png';
        $placeholderPath = 'products/placeholder_' . time() . '_' . Str::random(8) . '.' . $extension;
        
        // Create a simple text-based placeholder image
        $this->createSimplePlaceholderImage('Image Unavailable', $placeholderPath);
        
        return $placeholderPath;
    }
    
    private function createSimplePlaceholderImage(string $text, string $path): void
    {
        // Create a simple 400x400 PNG image with text
        $width = 400;
        $height = 400;
        
        // Create image
        $image = imagecreate($width, $height);
        
        // Define colors
        $backgroundColor = imagecolorallocate($image, 107, 114, 128); // Gray background
        $textColor = imagecolorallocate($image, 255, 255, 255); // White text
        
        // Fill background
        imagefill($image, 0, 0, $backgroundColor);
        
        // Add text
        $displayText = "Image\nUnavailable\n\n" . substr($text, 0, 20);
        $fontSize = 5;
        $textWidth = strlen($displayText) * imagefontwidth($fontSize);
        $textHeight = count(explode("\n", $displayText)) * imagefontheight($fontSize);
        
        $x = ($width - $textWidth) / 2;
        $y = ($height - $textHeight) / 2;
        
        // Split text by newlines and draw each line
        $lines = explode("\n", $displayText);
        foreach ($lines as $index => $line) {
            $lineY = $y + ($index * imagefontheight($fontSize));
            imagestring($image, $fontSize, $x, $lineY, $line, $textColor);
        }
        
        // Save image
        $fullPath = storage_path('app/public/' . $path);
        imagepng($image, $fullPath);
        imagedestroy($image);
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

    /**
     * Check if S3 is available and working
     */
    public function isS3Available(): bool
    {
        // Check if S3 is configured first
        $s3Config = config('filesystems.disks.s3');
        $hasCredentials = !empty($s3Config['key']) && 
                         !empty($s3Config['secret']) && 
                         !empty($s3Config['bucket']) && 
                         !empty($s3Config['region']);
        
        if (!$hasCredentials) {
            return false;
        }

        try {
            $disk = Storage::disk('s3');
            // Try to put a small test file to verify S3 connection
            $testContent = 'test';
            $testPath = 'test-connection-' . time() . '.txt';
            $disk->put($testPath, $testContent);
            $disk->delete($testPath); // Clean up test file
            return true;
        } catch (\Exception $e) {
            Log::warning('S3 connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get storage information
     */
    public function getStorageInfo(): array
    {
        // Check S3 configuration directly
        $s3Config = config('filesystems.disks.s3');
        $hasCredentials = !empty($s3Config['key']) && 
                         !empty($s3Config['secret']) && 
                         !empty($s3Config['bucket']) && 
                         !empty($s3Config['region']);
        
        $s3Configured = $hasCredentials;
        $s3Available = $this->isS3Available();
        $shouldUseS3 = $this->shouldUseS3();
        
        return [
            'default_disk' => config('filesystems.default'),
            's3_configured' => $s3Configured,
            's3_available' => $s3Available,
            'local_available' => true,
            'current_storage' => $shouldUseS3 && $s3Available ? 's3' : 'local',
            's3_preference_enabled' => $this->preferS3 || config('app.prefer_s3', false)
        ];
    }

    /**
     * Construct S3 URL based on configuration
     */
    private function constructS3Url(string $path): string
    {
        $s3Config = config('filesystems.disks.s3');
        
        // If custom URL is provided, use it
        if (!empty($s3Config['url'])) {
            return rtrim($s3Config['url'], '/') . '/' . $path;
        }
        
        // If bucket contains a full URL (like Contabo Storage), extract the base URL
        if (!empty($s3Config['bucket']) && filter_var($s3Config['bucket'], FILTER_VALIDATE_URL)) {
            $bucketUrl = $s3Config['bucket'];
            return rtrim($bucketUrl, '/') . '/' . $path;
        }
        
        // If custom endpoint is provided (for S3-compatible services)
        if (!empty($s3Config['endpoint'])) {
            $endpoint = rtrim($s3Config['endpoint'], '/');
            $bucket = $s3Config['bucket'];
            
            if ($s3Config['use_path_style_endpoint'] ?? false) {
                return "{$endpoint}/{$bucket}/{$path}";
            } else {
                return "{$endpoint}/{$path}";
            }
        }
        
        // Standard AWS S3 URL format
        $bucket = $s3Config['bucket'];
        $region = $s3Config['region'];
        return "https://{$bucket}.s3.{$region}.amazonaws.com/{$path}";
    }



    /**
     * Generate a signed URL for S3 files (required for Contabo Storage and similar services)
     */
    public function generateSignedS3Url(string $path, int $expiresIn = 3600): ?string
    {
        try {
            if (!$this->shouldUseS3()) {
                return null;
            }

            $s3Config = config('filesystems.disks.s3');
            
            // Create AWS S3 client directly
            $s3Client = new \Aws\S3\S3Client([
                'version' => 'latest',
                'region' => $s3Config['region'],
                'credentials' => [
                    'key' => $s3Config['key'],
                    'secret' => $s3Config['secret'],
                ],
                'endpoint' => $s3Config['endpoint'] ?? null,
                'use_path_style_endpoint' => $s3Config['use_path_style_endpoint'] ?? false,
            ]);
            
            // Extract bucket name
            $bucket = $this->extractBucketName($s3Config['bucket']);
            
            // Create command for GetObject
            $command = $s3Client->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key' => $path
            ]);
            
            // Generate signed URL
            $request = $s3Client->createPresignedRequest($command, "+{$expiresIn} seconds");
            $signedUrl = (string) $request->getUri();
            
            Log::info('Generated signed S3 URL', [
                'path' => $path,
                'expires_in' => $expiresIn,
                'url' => $signedUrl
            ]);
            
            return $signedUrl;
            
        } catch (\Exception $e) {
            Log::error('Failed to generate signed S3 URL', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extract bucket name from bucket configuration (handles full URLs)
     */
    private function extractBucketName(string $bucket): string
    {
        // If bucket contains a full URL, extract the bucket name
        if (filter_var($bucket, FILTER_VALIDATE_URL)) {
            $path = parse_url($bucket, PHP_URL_PATH);
            return trim($path, '/');
        }
        
        return $bucket;
    }


}
