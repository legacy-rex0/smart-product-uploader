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
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    private $aiService;
    private $fileUploadService;

    public function __construct()
    {
        $this->aiService = new AIService();
        $this->fileUploadService = new FileUploadService();
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
            $import = new ProductsImport();
            
            Excel::import($import, $request->file('excel_file'));

            $successCount = $import->getSuccessCount();
            $errors = $import->getErrors();

            return response()->json([
                'success' => true,
                'message' => "Bulk upload completed. {$successCount} products imported successfully.",
                'success_count' => $successCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during bulk upload: ' . $e->getMessage()
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
}
