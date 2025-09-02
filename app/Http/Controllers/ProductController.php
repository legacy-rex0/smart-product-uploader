<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\AIService;
use App\Services\FileUploadService;
use App\Imports\ProductsImport;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\BulkUploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Jobs\ProcessBulkProductUpload;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    private $aiService;
    private $fileUploadService;

    public function __construct()
    {
        $this->aiService = new AIService();
        $this->fileUploadService = new FileUploadService();
        
        // Enable S3 preference if S3 is available
        if ($this->fileUploadService->isS3Available()) {
            $this->fileUploadService->preferS3(true);
        }
    }

    public function index()
    {
        $products = Product::latest()->paginate(10);
        return view('products.index', compact('products'));
    }

    public function create()
    {
        return view('products.create');
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            $productName = $request->input('name');
            $description = $request->input('description');
            $image = $request->file('image');

            if (!$description) {
                $description = $this->aiService->generateDescription($productName);
            }

            $imageUrl = null;
            $imagePath = null;
            $isAiGeneratedImage = false;

            if ($image) {
                $uploadResult = $this->fileUploadService->uploadImage($image);
                $imageUrl = $uploadResult['url'];
                $imagePath = $uploadResult['path'];
            } elseif ($request->input('image_url')) {
                // Use provided image URL
                $imageUrl = $request->input('image_url');
                $imagePath = null;
                $isAiGeneratedImage = false;
            } else {
                $imageUrl = $this->aiService->generateImage($productName);
                $isAiGeneratedImage = true;
            }

            $product = Product::create([
                'name' => $productName,
                'description' => $description,
                'image_url' => $imageUrl,
                'image_path' => $imagePath,
                'is_ai_generated_description' => !$request->input('description'),
                'is_ai_generated_image' => $isAiGeneratedImage,
                'upload_method' => 'manual',
                'metadata' => [
                    'ai_generated' => [
                        'description' => !$request->input('description'),
                        'image' => $isAiGeneratedImage
                    ],
                    'ai_status' => [
                        'description' => !$request->input('description') ? 'generated' : 'provided',
                        'image' => $isAiGeneratedImage ? 'generated' : 'provided'
                    ]
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'product' => $product
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating product: ' . $e->getMessage()
            ], 500);
        }
    }

    public function bulkUpload(BulkUploadRequest $request): JsonResponse
    {
        try {
            $file = $request->file('excel_file');
            
            // Debug file information
            \Log::info('File upload details', [
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
                'temp_path' => $file->getPathname(),
                'temp_exists' => file_exists($file->getPathname())
            ]);
            
            // Validate file size (10MB max)
            if ($file->getSize() > 10 * 1024 * 1024) {
                return response()->json([
                    'success' => false,
                    'message' => 'File size exceeds 10MB limit'
                ], 400);
            }
            
            // Additional file type validation
            $allowedExtensions = ['xlsx', 'xls', 'csv', 'txt'];
            $fileExtension = strtolower($file->getClientOriginalExtension());
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File type not supported. Please use .xlsx, .xls, .csv, or .txt files.'
                ], 400);
            }
            
            // Store file in a more reliable location
            $filePath = $file->store('bulk-uploads', 'local');
            
            // Debug storage result
            \Log::info('File storage result', [
                'stored_path' => $filePath,
                'full_path' => storage_path('app/' . $filePath),
                'file_exists' => file_exists(storage_path('app/' . $filePath)),
                'bulk_uploads_dir_contents' => array_diff(scandir(storage_path('app/bulk-uploads')), ['.', '..'])
            ]);

            // Verify the file was stored successfully
            $fullPath = storage_path('app/' . $filePath);
            if (!file_exists($fullPath)) {
                \Log::error('File storage failed', [
                    'file_path' => $filePath,
                    'full_path' => $fullPath,
                    'bulk_uploads_dir_exists' => is_dir(storage_path('app/bulk-uploads')),
                    'bulk_uploads_dir_writable' => is_writable(storage_path('app/bulk-uploads'))
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Error: File was not stored successfully'
                ], 500);
            }

            // Check if queue is ready
            $pendingJobs = DB::table('jobs')->count();
            if ($pendingJobs > 0) {
                \Log::warning('Queue has pending jobs', ['pending_jobs' => $pendingJobs]);
            }

            // Create a unique job ID
            $jobId = 'bulk_upload_' . time() . '_' . uniqid();
            
            // Dispatch the job with the correct file path
            $job = ProcessBulkProductUpload::dispatch($filePath, Auth::id() ?? null, $jobId);

            \Log::info('Bulk upload job dispatched successfully', [
                'job_id' => $jobId,
                'file_path' => $filePath,
                'file_extension' => $fileExtension,
                'pending_jobs_after_dispatch' => DB::table('jobs')->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bulk upload started successfully. You can track progress below.',
                'data' => [
                    'file_path' => $filePath,
                    'job_id' => $jobId,
                    'file_type' => $fileExtension,
                    'tracking_url' => route('products.bulk-upload-progress', $jobId),
                    'queue_status' => [
                        'pending_jobs' => DB::table('jobs')->count(),
                        'queue_ready' => DB::table('jobs')->count() === 0
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Bulk upload error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error starting bulk upload: ' . $e->getMessage()
            ], 500);
        }
    }

    public function bulkUploadProgress($jobId): JsonResponse
    {
        try {
            $progress = Cache::get("bulk_upload_progress_{$jobId}");
            $results = Cache::get("bulk_upload_results_{$jobId}");

            // Debug information
            $debug = [
                'job_id' => $jobId,
                'progress_cache_key' => "bulk_upload_progress_{$jobId}",
                'results_cache_key' => "bulk_upload_results_{$jobId}",
                'progress_exists' => $progress ? true : false,
                'results_exists' => $results ? true : false,
                'cache_driver' => config('cache.default'),
                'timestamp' => now()->toISOString()
            ];

            if (!$progress && !$results) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found or expired',
                    'debug' => $debug
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'progress' => $progress,
                    'results' => $results,
                    'debug' => $debug
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching progress: ' . $e->getMessage(),
                'debug' => [
                    'job_id' => $jobId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    public function queueStatus(): JsonResponse
    {
        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'pending_jobs' => $pendingJobs,
                    'failed_jobs' => $failedJobs,
                    'queue_ready' => $pendingJobs === 0,
                    'timestamp' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking queue status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function startQueueWorker(): JsonResponse
    {
        try {
            // This is a simple implementation - in production you might want to use supervisor or similar
            $command = 'php ' . base_path('artisan') . ' queue:work --daemon > /dev/null 2>&1 &';
            exec($command);
            
            return response()->json([
                'success' => true,
                'message' => 'Queue worker started successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error starting queue worker: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Product $product)
    {
        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        return view('products.edit', compact('product'));
    }

    public function update(StoreProductRequest $request, Product $product): JsonResponse
    {
        try {
            $productName = $request->input('name');
            $description = $request->input('description');
            $image = $request->file('image');

            if (!$description) {
                $description = $this->aiService->generateDescription($productName);
            }

            $imageUrl = $product->image_url;
            $imagePath = $product->image_path;
            $isAiGeneratedImage = $product->is_ai_generated_image;

            if ($image) {
                $uploadResult = $this->fileUploadService->uploadImage($image);
                $imageUrl = $uploadResult['url'];
                $imagePath = $uploadResult['path'];
                $isAiGeneratedImage = false;
            } elseif ($request->input('image_url')) {
                // Use provided image URL
                $imageUrl = $request->input('image_url');
                $imagePath = null;
                $isAiGeneratedImage = false;
            }

            $product->update([
                'name' => $productName,
                'description' => $description,
                'image_url' => $imageUrl,
                'image_path' => $imagePath,
                'is_ai_generated_description' => !$request->input('description'),
                'is_ai_generated_image' => $isAiGeneratedImage,
                'metadata' => [
                    'ai_generated' => [
                        'description' => !$request->input('description'),
                        'image' => $isAiGeneratedImage
                    ],
                    'last_updated' => now()->toISOString()
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'product' => $product
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating product: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Product $product): JsonResponse
    {
        try {
            $productName = $product->name;
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => "Product '{$productName}' deleted successfully"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting product: ' . $e->getMessage()
            ], 500);
        }
    }

    public function apiIndex()
    {
        $products = Product::latest()->paginate(20);
        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function apiShow(Product $product)
    {
        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    public function apiStore(StoreProductRequest $request): JsonResponse
    {
        try {
            $productName = $request->input('name');
            $description = $request->input('description');
            $image = $request->file('image');

            if (!$description) {
                $description = $this->aiService->generateDescription($productName);
            }

            $imageUrl = null;
            $imagePath = null;
            $isAiGeneratedImage = false;

            if ($image) {
                $uploadResult = $this->fileUploadService->uploadImage($image);
                $imageUrl = $uploadResult['url'];
                $imagePath = $uploadResult['path'];
            } elseif ($request->input('image_url')) {
                // Use provided image URL
                $imageUrl = $request->input('image_url');
                $imagePath = null;
                $isAiGeneratedImage = false;
            } else {
                $imageUrl = $this->aiService->generateImage($productName);
                $isAiGeneratedImage = true;
            }

            $product = Product::create([
                'name' => $productName,
                'description' => $description,
                'image_url' => $imageUrl,
                'image_path' => $imagePath,
                'is_ai_generated_description' => !$request->input('description'),
                'is_ai_generated_image' => $isAiGeneratedImage,
                'upload_method' => 'api',
                'metadata' => [
                    'ai_generated' => [
                        'description' => !$request->input('description'),
                        'image' => $isAiGeneratedImage
                    ]
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating product: ' . $e->getMessage()
            ], 500);
        }
    }

    public function apiBulkUpload(BulkUploadRequest $request): JsonResponse
    {
        try {
            $import = new ProductsImport();
            
            Excel::import($import, $request->file('excel_file'));

            $successCount = $import->getSuccessCount();
            $errors = $import->getErrors();

            return response()->json([
                'success' => true,
                'message' => "Bulk upload completed. {$successCount} products imported successfully.",
                'data' => [
                    'success_count' => $successCount,
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during bulk upload: ' . $e->getMessage()
            ], 500);
        }
    }

    public function apiUpdate(StoreProductRequest $request, Product $product): JsonResponse
    {
        try {
            $productName = $request->input('name');
            $description = $request->input('description');
            $image = $request->file('image');

            if (!$description) {
                $description = $this->aiService->generateDescription($productName);
            }

            $imageUrl = $product->image_url;
            $imagePath = $product->image_path;
            $isAiGeneratedImage = $product->is_ai_generated_image;

            if ($image) {
                $uploadResult = $this->fileUploadService->uploadImage($image);
                $imageUrl = $uploadResult['url'];
                $imagePath = $uploadResult['path'];
                $isAiGeneratedImage = false;
            }

            $product->update([
                'name' => $productName,
                'description' => $description,
                'image_url' => $imageUrl,
                'image_path' => $imagePath,
                'is_ai_generated_description' => !$request->input('description'),
                'is_ai_generated_image' => $isAiGeneratedImage,
                'metadata' => [
                    'ai_generated' => [
                        'description' => !$request->input('description'),
                        'image' => $isAiGeneratedImage
                    ],
                    'last_updated' => now()->toISOString()
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating product: ' . $e->getMessage()
            ], 500);
        }
    }

    public function apiDestroy(Product $product): JsonResponse
    {
        try {
            $productName = $product->name;
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => "Product '{$productName}' deleted successfully"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting product: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current storage configuration status
     */
    public function getStorageStatus(): JsonResponse
    {
        try {
            $storageInfo = $this->fileUploadService->getStorageInfo();
            $s3Preference = $this->fileUploadService->getS3Preference();
            $shouldUseS3 = $this->fileUploadService->shouldUseS3();
            $isS3Available = $this->fileUploadService->isS3Available();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'storage_info' => $storageInfo,
                    's3_preference' => $s3Preference,
                    'should_use_s3' => $shouldUseS3,
                    'is_s3_available' => $isS3Available,
                    'current_storage_method' => $shouldUseS3 ? 's3' : 'local'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get storage status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
