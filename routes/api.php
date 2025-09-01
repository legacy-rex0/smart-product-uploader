<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::get('/products', [ProductController::class, 'apiIndex']);
    Route::get('/products/{product}', [ProductController::class, 'apiShow']);
    Route::post('/products', [ProductController::class, 'apiStore']);
    Route::post('/products/bulk-upload', [ProductController::class, 'apiBulkUpload']);
});
