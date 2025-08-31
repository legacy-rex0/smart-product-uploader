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
    }

    public function generateDescription(string $productName): ?string
    {
        if (!$this->openaiApiKey) {
            Log::warning('OpenAI API key not configured, using mock description');
            return $this->getMockDescription($productName);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])->post($this->openaiBaseUrl . '/chat/completions', [
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

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }

            Log::error('OpenAI API error', ['response' => $response->body()]);
            return $this->getMockDescription($productName);
        } catch (\Exception $e) {
            Log::error('OpenAI API exception', ['error' => $e->getMessage()]);
            return $this->getMockDescription($productName);
        }
    }

    public function generateImage(string $productName): ?string
    {
        if (!$this->openaiApiKey) {
            Log::warning('OpenAI API key not configured, using mock image');
            return $this->getMockImageUrl($productName);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])->post($this->openaiBaseUrl . '/images/generations', [
                'prompt' => "Professional product photography of {$productName}, clean background, high quality, commercial use",
                'n' => 1,
                'size' => '1024x1024',
                'response_format' => 'url'
            ]);

            if ($response->successful()) {
                return $response->json('data.0.url');
            }

            Log::error('OpenAI DALL-E API error', ['response' => $response->body()]);
            return $this->getMockImageUrl($productName);
        } catch (\Exception $e) {
            Log::error('OpenAI DALL-E API exception', ['error' => $e->getMessage()]);
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
        $colors = ['red', 'blue', 'green', 'purple', 'orange'];
        $color = $colors[array_rand($colors)];
        $size = '400x400';
        
        return "https://via.placeholder.com/{$size}/{$color}/ffffff?text=" . urlencode($productName);
    }
}
