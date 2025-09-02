<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\DashboardController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::resource('products', ProductController::class);
Route::post('products/bulk-upload', [ProductController::class, 'bulkUpload'])->name('products.bulk-upload');
Route::get('products/bulk-upload/{jobId}/progress', [ProductController::class, 'bulkUploadProgress'])->name('products.bulk-upload-progress');
Route::get('products/queue-status', [ProductController::class, 'queueStatus'])->name('products.queue-status');
Route::post('products/start-queue-worker', [ProductController::class, 'startQueueWorker'])->name('products.start-queue-worker');
Route::get('/products/storage-status', [ProductController::class, 'getStorageStatus'])->name('products.storage-status');
