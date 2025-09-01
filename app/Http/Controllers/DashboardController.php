<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $totalProducts = Product::count();
        $aiGeneratedCount = Product::where('is_ai_generated_description', true)
            ->orWhere('is_ai_generated_image', true)
            ->count();
        $uploadMethodsCount = Product::distinct('upload_method')->count('upload_method');
        $recentProducts = Product::latest()->take(10)->get();

        return view('dashboard', compact(
            'totalProducts',
            'aiGeneratedCount',
            'uploadMethodsCount',
            'recentProducts'
        ));
    }
}
