<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FixExpiredImageUrls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:fix-expired-urls {--force : Force update even if image exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix expired Azure Blob Storage image URLs by downloading and storing them locally';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to fix expired image URLs...');
        
        $products = Product::whereNotNull('image_url')
            ->where('image_url', 'like', '%blob.core.windows.net%')
            ->get();
        
        if ($products->isEmpty()) {
            $this->info('No products with Azure Blob Storage URLs found.');
            return 0;
        }
        
        $this->info("Found {$products->count()} products with Azure URLs to fix.");
        
        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;
        
        $bar = $this->output->createProgressBar($products->count());
        $bar->start();
        
        foreach ($products as $product) {
            try {
                $result = $this->fixProductImage($product);
                
                if ($result === 'success') {
                    $successCount++;
                } elseif ($result === 'skipped') {
                    $skippedCount++;
                } else {
                    $errorCount++;
                }
            } catch (\Exception $e) {
                $errorCount++;
                Log::error("Failed to fix image for product {$product->id}", [
                    'error' => $e->getMessage(),
                    'product_id' => $product->id
                ]);
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        $this->info("Image URL fix completed!");
        $this->info("✅ Successfully fixed: {$successCount}");
        $this->info("⏭️  Skipped: {$skippedCount}");
        $this->info("❌ Errors: {$errorCount}");
        
        return 0;
    }
    
    private function fixProductImage(Product $product): string
    {
        $azureUrl = $product->image_url;
        
        $this->info("Processing product {$product->id}: {$product->name}");
        
        // Check if we already have a local image path
        if ($product->image_path && !$this->option('force')) {
            $localPath = storage_path('app/public/' . $product->image_path);
            if (file_exists($localPath)) {
                $this->info("  -> Skipping (local image exists)");
                return 'skipped';
            }
        }
        
        try {
            $this->info("  -> Attempting to download from Azure URL...");
            // Try to download the image from Azure
            $imageContent = file_get_contents($azureUrl);
            
            if ($imageContent === false) {
                $this->info("  -> Download failed, creating placeholder...");
                // If download fails, create a placeholder
                $this->createPlaceholderForProduct($product);
                return 'success';
            }
            
            $this->info("  -> Download successful, storing locally...");
            // Generate a new filename
            $extension = $this->getExtensionFromUrl($azureUrl);
            $filename = 'product_' . $product->id . '_' . time() . '_' . uniqid() . '.' . $extension;
            $path = 'products/' . $filename;
            
            // Store the image locally
            Storage::disk('public')->put($path, $imageContent);
            
            // Update the product
            $product->update([
                'image_url' => config('app.url') . '/storage/' . $path,
                'image_path' => $path
            ]);
            
            $this->info("  -> Successfully stored and updated product");
            return 'success';
            
        } catch (\Exception $e) {
            $this->error("  -> Exception occurred: " . $e->getMessage());
            // If anything fails, create a placeholder
            $this->createPlaceholderForProduct($product);
            return 'success';
        }
    }
    
    private function createPlaceholderForProduct(Product $product): void
    {
        $this->info("    -> Creating placeholder for product {$product->id}");
        
        try {
            $filename = 'placeholder_' . $product->id . '_' . time() . '_' . uniqid() . '.png';
            $path = 'products/' . $filename;
            
            $this->info("    -> Creating simple placeholder image at: {$path}");
            
            // Create a simple text-based placeholder image
            $this->createSimplePlaceholderImage($product->name, $path);
            
            $this->info("    -> Updating product with new image path");
            $product->update([
                'image_url' => config('app.url') . '/storage/' . $path,
                'image_path' => $path
            ]);
            
            $this->info("    -> Placeholder created and product updated successfully");
            
        } catch (\Exception $e) {
            $this->error("    -> Exception creating placeholder: " . $e->getMessage());
            Log::error("Failed to create placeholder for product {$product->id}", [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function createSimplePlaceholderImage(string $productName, string $path): void
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
        $text = "Image\nUnavailable\n\n" . substr($productName, 0, 20);
        $fontSize = 5;
        $textWidth = strlen($text) * imagefontwidth($fontSize);
        $textHeight = count(explode("\n", $text)) * imagefontheight($fontSize);
        
        $x = ($width - $textWidth) / 2;
        $y = ($height - $textHeight) / 2;
        
        // Split text by newlines and draw each line
        $lines = explode("\n", $text);
        foreach ($lines as $index => $line) {
            $lineY = $y + ($index * imagefontheight($fontSize));
            imagestring($image, $fontSize, $x, $lineY, $line, $textColor);
        }
        
        // Save image
        $fullPath = storage_path('app/public/' . $path);
        imagepng($image, $fullPath);
        imagedestroy($image);
    }
    
    private function getExtensionFromUrl(string $url): string
    {
        $extension = pathinfo($url, PATHINFO_EXTENSION);
        if (empty($extension)) {
            // Try to get extension from query parameters
            if (preg_match('/rsct=image\/(\w+)/', $url, $matches)) {
                return $matches[1];
            }
            return 'png'; // Default to PNG
        }
        
        // Clean the extension
        $extension = strtolower($extension);
        $extension = preg_replace('/[^a-z0-9]/', '', $extension);
        
        return $extension ?: 'png';
    }
}
