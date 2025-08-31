<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;

Route::get('/', function () {
    return redirect()->route('products.index');
});

Route::resource('products', ProductController::class)->except(['edit', 'update', 'destroy']);
Route::post('products/bulk-upload', [ProductController::class, 'bulkUpload'])->name('products.bulk-upload');
