<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Product;
use App\Services\AIService;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log as LogFacade;

class ProcessBulkProductUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;
    
    protected $filePath;
    protected $userId;
    public $jobId;

    public function __construct($filePath, $userId = null, $jobId = null)
    {
        $this->filePath = $filePath;
        $this->userId = $userId;
        $this->jobId = $jobId ?? 'bulk_upload_' . time() . '_' . uniqid();
        
        // Create initial progress data immediately when job is dispatched
        $this->createInitialProgress();
    }

    public function handle()
    {
        try {
            LogFacade::info('Starting bulk product upload job', [
                'job_id' => $this->jobId,
                'file_path' => $this->filePath,
                'user_id' => $this->userId
            ]);

            // Initialize progress tracking
            $this->updateProgress(0, 'Starting import...');
            
            // Read the file to get total rows
            $totalRows = $this->countRows();
            $this->updateProgress(5, "Found {$totalRows} products to process");

            // Read all products from file
            $products = $this->readProductsFromFile();
            $this->updateProgress(10, "Read {$totalRows} products from file");

            if (empty($products)) {
                $this->updateProgress(100, "No products found in file");
                $this->storeResults(0, ["No valid products found in file"], $totalRows);
                return;
            }

            $processed = 0;
            $errors = [];
            $successCount = 0;
            $batchSize = 5; // Process in small batches

            // Process products in batches
            for ($i = 0; $i < count($products); $i += $batchSize) {
                $batch = array_slice($products, $i, $batchSize);
                $batchNumber = ($i / $batchSize) + 1;
                $totalBatches = ceil(count($products) / $batchSize);
                
                $this->updateProgress(
                    15 + (($i / count($products)) * 70), 
                    "Processing batch {$batchNumber} of {$totalBatches} ({$totalRows} total products)"
                );

                foreach ($batch as $index => $row) {
                    $globalIndex = $i + $index;
                    try {
                        $this->updateProgress(
                            15 + (($globalIndex / count($products)) * 70),
                            "Processing product " . ($globalIndex + 1) . " of {$totalRows}: {$row['product_name']}"
                        );

                        $product = $this->processProductRow($row);
                        if ($product) {
                            $successCount++;
                            LogFacade::info('Product processed successfully', [
                                'job_id' => $this->jobId,
                                'product_name' => $product->name,
                                'row' => $globalIndex + 1,
                                'batch' => $batchNumber
                            ]);
                        }
                        $processed++;

                    } catch (\Exception $e) {
                        $errorMsg = "Row " . ($globalIndex + 1) . " ({$row['product_name']}): " . $e->getMessage();
                        $errors[] = $errorMsg;
                        LogFacade::error('Error processing product row', [
                            'job_id' => $this->jobId,
                            'row' => $globalIndex + 1,
                            'product_name' => $row['product_name'],
                            'error' => $e->getMessage(),
                            'batch' => $batchNumber
                        ]);
                    }
                }

                // Small delay between batches to prevent overwhelming the system
                if ($i + $batchSize < count($products)) {
                    usleep(500000); // 0.5 second delay between batches
                }
            }

            $this->updateProgress(95, 'Finalizing import...');
            
            // Store final results
            $this->storeResults($successCount, $errors, $totalRows);
            
            $this->updateProgress(100, "Import completed! {$successCount} products imported successfully.");

            LogFacade::info('Bulk product upload job completed', [
                'job_id' => $this->jobId,
                'success_count' => $successCount,
                'error_count' => count($errors),
                'total_rows' => $totalRows,
                'processed' => $processed
            ]);

        } catch (\Exception $e) {
            LogFacade::error('Bulk product upload job failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->updateProgress(100, 'Import failed: ' . $e->getMessage());
            $this->storeResults(0, [$e->getMessage()], 0);
            
            throw $e;
        }
    }

    protected function getFullFilePath()
    {
        // If it's already an absolute path, return as is
        if (file_exists($this->filePath)) {
            return $this->filePath;
        }
        
        // If it's a relative path, construct the full path
        if (strpos($this->filePath, '/') !== 0) {
            $fullPath = storage_path('app/' . $this->filePath);
            
            // Log the file path for debugging
            LogFacade::info('Resolving file path', [
                'original_path' => $this->filePath,
                'full_path' => $fullPath,
                'exists' => file_exists($fullPath),
                'storage_path' => storage_path('app/'),
                'bulk_uploads_dir_exists' => is_dir(storage_path('app/bulk-uploads'))
            ]);
            
            return $fullPath;
        }
        
        return $this->filePath;
    }

    protected function countRows()
    {
        $fullPath = $this->getFullFilePath();
        
        if (!file_exists($fullPath)) {
            throw new \Exception("File not found: {$fullPath}");
        }
        
        $file = fopen($fullPath, 'r');
        if (!$file) {
            throw new \Exception("Could not open file: {$fullPath}");
        }
        
        $count = 0;
        while (fgetcsv($file) !== false) {
            $count++;
        }
        fclose($file);
        return max(0, $count - 1); // Subtract header row
    }

    protected function readProductsFromFile()
    {
        $fullPath = $this->getFullFilePath();
        
        if (!file_exists($fullPath)) {
            throw new \Exception("File not found: {$fullPath}");
        }
        
        $products = [];
        $file = fopen($fullPath, 'r');
        
        if (!$file) {
            throw new \Exception("Could not open file: {$fullPath}");
        }
        
        // Skip header row
        $header = fgetcsv($file);
        if (!$header) {
            fclose($file);
            throw new \Exception("Could not read header row from file");
        }
        
        $rowNumber = 1; // Start from 1 since we're after header
        while (($row = fgetcsv($file)) !== false) {
            $rowNumber++;
            
            try {
                // Clean and validate the row data
                $cleanRow = $this->cleanRowData($row);
                
                if (count($cleanRow) >= 1 && !empty(trim($cleanRow[0]))) {
                    $products[] = [
                        'product_name' => trim($cleanRow[0]),
                        'description' => isset($cleanRow[1]) ? trim($cleanRow[1]) : null,
                        'image_url' => isset($cleanRow[2]) ? trim($cleanRow[2]) : null,
                        'row_number' => $rowNumber
                    ];
                }
            } catch (\Exception $e) {
                LogFacade::warning('Skipping invalid row', [
                    'job_id' => $this->jobId,
                    'row_number' => $rowNumber,
                    'row_data' => $row,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
        
        fclose($file);
        return $products;
    }

    protected function cleanRowData($row)
    {
        $cleanRow = [];
        foreach ($row as $index => $value) {
            if ($value !== null) {
                // Convert to UTF-8 and remove any invalid characters
                $cleanValue = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                $cleanValue = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleanValue);
                $cleanRow[$index] = $cleanValue;
            } else {
                $cleanRow[$index] = '';
            }
        }
        return $cleanRow;
    }

    protected function processProductRow($row)
    {
        $productName = $row['product_name'] ?? null;
        
        if (!$productName) {
            throw new \Exception("Missing product name");
        }

        $description = $row['description'] ?? null;
        $imageUrl = $row['image_url'] ?? null;
        
        LogFacade::info('Processing product row', [
            'job_id' => $this->jobId,
            'product_name' => $productName,
            'has_description' => !empty($description),
            'has_image_url' => !empty($imageUrl),
            'row_number' => $row['row_number'] ?? 'unknown'
        ]);
        
        // Generate description if missing (with timeout protection)
        if (!$description) {
            try {
                LogFacade::info('Generating AI description for product', [
                    'job_id' => $this->jobId,
                    'product_name' => $productName
                ]);
                $description = $this->generateDescriptionWithTimeout($productName);
                LogFacade::info('AI description generated successfully', [
                    'job_id' => $this->jobId,
                    'product_name' => $productName,
                    'description_length' => strlen($description)
                ]);
            } catch (\Exception $e) {
                LogFacade::warning('AI description generation failed, using fallback', [
                    'job_id' => $this->jobId,
                    'product_name' => $productName,
                    'error' => $e->getMessage()
                ]);
                $description = $this->getFallbackDescription($productName);
            }
        }
        
        // Generate image if missing (with timeout protection)
        if (!$imageUrl) {
            try {
                LogFacade::info('Generating AI image for product', [
                    'job_id' => $this->jobId,
                    'product_name' => $productName
                ]);
                $imageUrl = $this->generateImageWithTimeout($productName);
                LogFacade::info('AI image generated successfully', [
                    'job_id' => $this->jobId,
                    'product_name' => $productName,
                    'image_url' => $imageUrl
                ]);
            } catch (\Exception $e) {
                LogFacade::warning('AI image generation failed, using fallback', [
                    'job_id' => $this->jobId,
                    'product_name' => $productName,
                    'error' => $e->getMessage()
                ]);
                $imageUrl = $this->getFallbackImageUrl($productName);
            }
        }

        // Prepare metadata safely
        $metadata = [
            'original_row' => [
                'product_name' => $productName,
                'description' => $row['description'] ?? null,
                'image_url' => $row['image_url'] ?? null,
                'row_number' => $row['row_number'] ?? null
            ],
            'ai_generated' => [
                'description' => !($row['description'] ?? null),
                'image' => !($row['image_url'] ?? null)
            ],
            'job_id' => $this->jobId,
            'uploaded_at' => now()->toISOString()
        ];

        // Create product
        $product = Product::create([
            'name' => $productName,
            'description' => $description,
            'image_url' => $imageUrl,
            'image_path' => null,
            'is_ai_generated_description' => !($row['description'] ?? null),
            'is_ai_generated_image' => !($row['image_url'] ?? null),
            'upload_method' => 'excel',
            'metadata' => $metadata
        ]);

        LogFacade::info('Product created successfully', [
            'job_id' => $this->jobId,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'ai_description' => !($row['description'] ?? null),
            'ai_image' => !($row['image_url'] ?? null)
        ]);

        return $product;
    }

    protected function generateDescriptionWithTimeout($productName)
    {
        try {
            $aiService = new AIService();
            return $aiService->generateDescription($productName);
        } catch (\Exception $e) {
            LogFacade::warning('AI description generation failed, using fallback', [
                'product_name' => $productName,
                'error' => $e->getMessage()
            ]);
            return $this->getFallbackDescription($productName);
        }
    }

    protected function generateImageWithTimeout($productName)
    {
        try {
            $aiService = new AIService();
            return $aiService->generateImage($productName);
        } catch (\Exception $e) {
            LogFacade::warning('AI image generation failed, using fallback', [
                'product_name' => $productName,
                'error' => $e->getMessage()
            ]);
            return $this->getFallbackImageUrl($productName);
        }
    }

    protected function getFallbackDescription($productName)
    {
        return "A high-quality {$productName} designed for optimal performance and user satisfaction. This premium product offers exceptional value and reliability for your needs.";
    }

    protected function getFallbackImageUrl($productName)
    {
        return "https://placehold.co/400x400?text=" . urlencode($productName);
    }

    protected function createInitialProgress()
    {
        $progress = [
            'percentage' => 0,
            'message' => 'Job queued and waiting to start...',
            'timestamp' => now()->toISOString(),
            'job_id' => $this->jobId,
            'status' => 'queued'
        ];
        
        // Store progress with longer TTL
        Cache::put("bulk_upload_progress_{$this->jobId}", $progress, 86400); // 24 hours
        
        LogFacade::info('Initial progress created', [
            'job_id' => $this->jobId,
            'cache_key' => "bulk_upload_progress_{$this->jobId}"
        ]);
    }

    protected function updateProgress($percentage, $message)
    {
        $progress = [
            'percentage' => $percentage,
            'message' => $message,
            'timestamp' => now()->toISOString(),
            'job_id' => $this->jobId,
            'status' => 'processing'
        ];
        
        Cache::put("bulk_upload_progress_{$this->jobId}", $progress, 86400); // 24 hours
        
        LogFacade::info('Progress updated', [
            'job_id' => $this->jobId,
            'percentage' => $percentage,
            'message' => $message
        ]);
    }

    protected function storeResults($successCount, $errors, $totalRows)
    {
        $results = [
            'success_count' => $successCount,
            'error_count' => count($errors),
            'total_rows' => $totalRows,
            'errors' => $errors,
            'completed_at' => now()->toISOString(),
            'status' => count($errors) === 0 ? 'completed' : 'completed_with_errors'
        ];
        
        Cache::put("bulk_upload_results_{$this->jobId}", $results, 86400); // 24 hours
    }

    public function failed(\Throwable $exception)
    {
        LogFacade::error('Bulk product upload job failed', [
            'job_id' => $this->jobId,
            'error' => $exception->getMessage()
        ]);

        $this->updateProgress(100, 'Import failed: ' . $exception->getMessage());
        $this->storeResults(0, [$exception->getMessage()], 0);
    }
}
