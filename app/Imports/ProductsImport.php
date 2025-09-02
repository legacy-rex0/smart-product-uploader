<?php

namespace App\Imports;

use App\Models\Product;
use App\Services\AIService;
use App\Services\FileUploadService;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Illuminate\Support\Facades\Log;

class ProductsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError
{
    private $aiService;
    private $fileUploadService;
    private $errors = [];
    private $successCount = 0;

    public function __construct()
    {
        $this->aiService = new AIService();
        $this->fileUploadService = new FileUploadService();
        
        // Enable S3 preference if S3 is available
        if ($this->fileUploadService->shouldUseS3() || $this->fileUploadService->isS3Available()) {
            $this->fileUploadService->setPersistentS3Preference(true);
        }
    }

    public function model(array $row)
    {
        try {
            $productName = $row['product_name'] ?? $row['name'] ?? null;
            
            if (!$productName) {
                $this->errors[] = "Row missing product name: " . json_encode($row);
                return null;
            }

            $description = $row['description'] ?? null;
            $imageUrl = $row['image_url'] ?? null;
            
            if (!$description) {
                $description = $this->aiService->generateDescription($productName);
            }
            
            if (!$imageUrl) {
                $imageUrl = $this->aiService->generateImage($productName);
            }

            $uploadResult = null;
            if ($imageUrl && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $uploadResult = $this->fileUploadService->uploadImageFromUrl($imageUrl);
                $imageUrl = $uploadResult['url'];
            }

            $product = new Product([
                'name' => $productName,
                'description' => $description,
                'image_url' => $imageUrl,
                'image_path' => $uploadResult['path'] ?? null,
                'is_ai_generated_description' => !($row['description'] ?? null),
                'is_ai_generated_image' => !($row['image_url'] ?? null),
                'upload_method' => 'excel',
                'metadata' => [
                    'original_row' => $row,
                    'ai_generated' => [
                        'description' => !($row['description'] ?? null),
                        'image' => !($row['image_url'] ?? null)
                    ]
                ]
            ]);

            $this->successCount++;
            return $product;

        } catch (\Exception $e) {
            Log::error('Product import error', [
                'row' => $row,
                'error' => $e->getMessage()
            ]);
            $this->errors[] = "Error processing row: " . $e->getMessage();
            return null;
        }
    }

    public function rules(): array
    {
        return [
            'product_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string'
        ];
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function onError(\Throwable $e)
    {
        $this->errors[] = "Import error: " . $e->getMessage();
        Log::error('Excel import error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}
