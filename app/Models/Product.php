<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'description',
        'image_url',
        'image_path',
        'is_ai_generated_description',
        'is_ai_generated_image',
        'upload_method',
        'metadata'
    ];

    protected $casts = [
        'is_ai_generated_description' => 'boolean',
        'is_ai_generated_image' => 'boolean',
        'metadata' => 'array'
    ];
}
