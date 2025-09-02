<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    private $openaiApiKey;
    private $openaiBaseUrl;

    public function __construct()
    {
        $this->openaiApiKey = config('services.openai.api_key');
        $this->openaiBaseUrl = config('services.openai.base_url', 'https://api.openai.com/v1');

        Log::info('AIService initialized', [
            'has_api_key' => !empty($this->openaiApiKey),
            'api_key_length' => strlen($this->openaiApiKey ?? ''),
            'base_url' => $this->openaiBaseUrl
        ]);
    }

    public function generateDescription(string $productName): ?string
    {
        Log::info('Generating description for product', ['product_name' => $productName]);
        
        if (!$this->openaiApiKey) {
            Log::warning('OpenAI API key not configured, using mock description');
            return $this->getMockDescription($productName);
        }

        try {
            Log::info('Making OpenAI API request for description', [
                'url' => $this->openaiBaseUrl . '/chat/completions',
                'model' => 'gpt-3.5-turbo',
                'product_name' => $productName
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post($this->openaiBaseUrl . '/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a product description expert. Generate a compelling, SEO-friendly product description based on the product name. Keep it under 150 words and focus on benefits and features.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Generate a product description for: {$productName}"
                    ]
                ],
                'max_tokens' => 200,
                'temperature' => 0.7
            ]);

            Log::info('OpenAI API response received', [
                'status' => $response->status(),
                'success' => $response->successful(),
                'product_name' => $productName
            ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                Log::info('Description generated successfully', [
                    'content_length' => strlen($content),
                    'product_name' => $productName
                ]);
                return $content;
            }

            $errorResponse = $response->json();
            $errorMessage = $errorResponse['error']['message'] ?? 'Unknown error';
            
            if ($response->status() === 429 || str_contains($errorMessage, 'quota') || str_contains($errorMessage, 'billing')) {
                Log::warning('OpenAI API quota exceeded, using mock description', [
                    'status' => $response->status(),
                    'error' => $errorMessage,
                    'product_name' => $productName
                ]);
            } else {
                Log::error('OpenAI API error', [
                    'status' => $response->status(),
                    'error' => $errorMessage,
                    'product_name' => $productName
                ]);
            }
            
            return $this->getMockDescription($productName);
        } catch (\Exception $e) {
            Log::error('OpenAI API exception', [
                'error' => $e->getMessage(),
                'product_name' => $productName
            ]);
            return $this->getMockDescription($productName);
        }
    }

    public function generateImage(string $productName): ?string
    {
        Log::info('Generating image for product', ['product_name' => $productName]);
        
        if (!$this->openaiApiKey) {
            Log::warning('OpenAI API key not configured, using mock image');
            return $this->getMockImageUrl($productName);
        }

        try {
            Log::info('Making OpenAI DALL-E API request', [
                'url' => $this->openaiBaseUrl . '/images/generations',
                'prompt' => "Professional product photography of {$productName}, clean background, high quality, commercial use",
                'product_name' => $productName
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post($this->openaiBaseUrl . '/images/generations', [
                'prompt' => "Professional product photography of {$productName}, clean background, high quality, commercial use",
                'n' => 1,
                'size' => '1024x1024',
                'response_format' => 'url'
            ]);

            Log::info('OpenAI DALL-E API response received', [
                'status' => $response->status(),
                'success' => $response->successful(),
                'product_name' => $productName
            ]);

            if ($response->successful()) {
                $imageUrl = $response->json('data.0.url');
                Log::info('Image generated successfully', [
                    'image_url' => $imageUrl,
                    'product_name' => $productName
                ]);
                return $imageUrl;
            }

            $errorResponse = $response->json();
            $errorMessage = $errorResponse['error']['message'] ?? 'Unknown error';
            
            if ($response->status() === 429 || str_contains($errorMessage, 'quota') || str_contains($errorMessage, 'billing')) {
                Log::warning('OpenAI DALL-E API quota exceeded, using mock image', [
                    'status' => $response->status(),
                    'error' => $errorMessage,
                    'product_name' => $productName
                ]);
            } elseif ($response->status() >= 500) {
                Log::warning('OpenAI DALL-E API server error, using mock image', [
                    'status' => $response->status(),
                    'error' => $errorMessage,
                    'product_name' => $productName
                ]);
            } else {
                Log::error('OpenAI DALL-E API error', [
                    'status' => $response->status(),
                    'error' => $errorMessage,
                    'product_name' => $productName
                ]);
            }
            
            return $this->getMockImageUrl($productName);
        } catch (\Exception $e) {
            Log::error('OpenAI DALL-E API exception', [
                'error' => $e->getMessage(),
                'product_name' => $productName
            ]);
            return $this->getMockImageUrl($productName);
        }
    }

    private function getMockDescription(string $productName): string
    {
        $descriptions = [
            "Discover the amazing {$productName} - a premium quality product designed to enhance your daily life. Built with superior craftsmanship and innovative technology, this exceptional item offers unmatched performance and reliability. Perfect for both personal and professional use, it combines style with functionality to meet all your needs.",
            "Experience excellence with the {$productName}. This thoughtfully designed product features cutting-edge technology and premium materials, ensuring durability and superior performance. Whether you're at home or on the go, this versatile solution adapts to your lifestyle while maintaining the highest standards of quality.",
            "The {$productName} represents the perfect blend of innovation and practicality. Crafted with attention to detail, this outstanding product delivers exceptional value and performance. Its user-friendly design and robust construction make it an ideal choice for discerning customers who demand the best."
        ];

        return $descriptions[array_rand($descriptions)];
    }

    private function getMockImageUrl(string $productName): string
    {
        $services = [
            "https://picsum.photos/400/400?random=" . rand(1, 1000),
            "https://source.unsplash.com/400x400/?product," . urlencode($productName),
            "https://via.placeholder.com/400x400/4F46E5/ffffff?text=" . urlencode($productName),
            "https://via.placeholder.com/400x400/059669/ffffff?text=" . urlencode($productName),
            "https://via.placeholder.com/400x400/DC2626/ffffff?text=" . urlencode($productName)
        ];
        
        return $services[array_rand($services)];
    }
}
