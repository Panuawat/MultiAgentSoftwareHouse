<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey  = config('services.gemini.api_key');
        $this->model   = config('services.gemini.model', 'gemini-2.0-flash');
        $this->baseUrl = config('services.gemini.base_url');
    }

    /**
     * Call Gemini with a system prompt and a user message.
     * Returns ['content' => array, 'tokens_used' => int].
     *
     * @throws RuntimeException on API error or non-JSON response
     */
    public function generate(string $systemPrompt, string $userMessage): array
    {
        $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

        $response = Http::timeout(60)->post($url, [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [
                ['parts' => [['text' => $userMessage]]],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Gemini API error '.$response->status().': '.$response->body()
            );
        }

        $data = $response->json();

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($text === null) {
            throw new RuntimeException('Gemini returned no text content: '.json_encode($data));
        }

        $parsed = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Gemini response is not valid JSON: '.$text);
        }

        $tokensUsed = $data['usageMetadata']['totalTokenCount'] ?? 0;

        return [
            'content'     => $parsed,
            'tokens_used' => (int) $tokensUsed,
        ];
    }
}
