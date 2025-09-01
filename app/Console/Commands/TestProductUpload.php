<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AIService;
use App\Services\FileUploadService;
use Illuminate\Console\Command;

class TestProductUpload extends Command
{
    protected $signature = 'test:product-upload {--count=5}';
    protected $description = 'Test product upload functionality with sample data';

    public function handle()
    {
        $count = (int) $this->option('count');
        $this->info("Testing product upload with {$count} sample products...");

        $aiService = new AIService();
        $fileUploadService = new FileUploadService();

        $sampleProducts = [
            'Wireless Headphones',
            'Smart Watch',
            'Laptop Stand',
            'Coffee Maker',
            'Yoga Mat',
            'Bluetooth Speaker',
            'Phone Charger',
            'Desk Lamp',
            'Water Bottle',
            'Backpack'
        ];

        $bar = $this->output->createProgressBar(min($count, count($sampleProducts)));
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            $productName = $sampleProducts[$i] ?? "Test Product " . ($i + 1);
            
            try {
                $description = $aiService->generateDescription($productName);
                $imageUrl = $aiService->generateImage($productName);

                $product = Product::create([
                    'name' => $productName,
                    'description' => $description,
                    'image_url' => $imageUrl,
                    'is_ai_generated_description' => true,
                    'is_ai_generated_image' => true,
                    'upload_method' => 'command',
                    'metadata' => [
                        'test_run' => true,
                        'ai_generated' => [
                            'description' => true,
                            'image' => true
                        ]
                    ]
                ]);

                $bar->advance();
            } catch (\Exception $e) {
                $this->error("Failed to create product {$productName}: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("Test completed! Created {$count} sample products.");
        
        $totalProducts = Product::count();
        $this->info("Total products in database: {$totalProducts}");
    }
}
