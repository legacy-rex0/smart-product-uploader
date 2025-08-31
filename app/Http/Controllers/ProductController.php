<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\AIService;
use App\Services\FileUploadService;
use App\Imports\ProductsImport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;

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

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

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

    public function bulkUpload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

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
}
