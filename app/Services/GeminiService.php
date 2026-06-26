<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GeminiService
{
    private array $apiKeys;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        // Support multiple keys: GEMINI_API_KEY, GEMINI_API_KEY_2, GEMINI_API_KEY_3, etc.
        $this->apiKeys = array_filter([
            config('services.gemini.api_key'),
            config('services.gemini.api_key_2'),
            config('services.gemini.api_key_3'),
        ]);
        $this->model = config('services.gemini.model', 'gemini-flash-latest');
        $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    }

    public function generateContent(string $systemPrompt, string $userPrompt): ?string
    {
        // Cache: same input = same response, no API call wasted
        $cacheKey = 'gemini_' . md5($userPrompt);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
            ],
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'topP' => 0.8,
                'maxOutputTokens' => 2048,
                'responseMimeType' => 'application/json',
            ],
        ];

        $startTime = microtime(true);
        $maxTotalTime = 15; // Never spend more than 15s total on API calls

        // Try each key, rotate on rate limit
        foreach ($this->apiKeys as $keyIndex => $apiKey) {
            if (empty($apiKey)) continue;
            if ((microtime(true) - $startTime) > $maxTotalTime) break;

            // Skip keys that were recently rate-limited (cooldown 60s)
            if (Cache::get("gemini_key_blocked_{$keyIndex}")) {
                continue;
            }

            $result = $this->callApi($apiKey, $payload);
            if ($result !== null) {
                Cache::put($cacheKey, $result, 3600);
                return $result;
            }

            // If rate limited, try next key immediately
            if (Cache::get("gemini_key_blocked_{$keyIndex}")) {
                continue;
            }
        }

        Log::warning('All Gemini keys exhausted or timed out');
        return null;
    }

    private function callApi(string $apiKey, array $payload): ?string
    {
        $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$apiKey}";
        try {
            $response = Http::timeout(8)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);

            if ($response->status() === 429) {
                // Rate limited — mark this key as blocked for 60s
                $keyIndex = array_search($apiKey, $this->apiKeys);
                if ($keyIndex !== false) {
                    Cache::put("gemini_key_blocked_{$keyIndex}", true, 60);
                }
                Log::warning('Gemini 429 rate limited', ['key_index' => $keyIndex]);
                return null;
            }

            if ($response->failed()) {
                Log::error('Gemini API error', ['status' => $response->status()]);
                return null;
            }

            $data = $response->json();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        } catch (\Exception $e) {
            Log::error('Gemini exception', ['msg' => $e->getMessage()]);
            return null;
        }
    }
}
