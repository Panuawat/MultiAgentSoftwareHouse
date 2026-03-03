<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaService
{
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->baseUrl = config('services.ollama.base_url', 'http://localhost:11434');
        $this->model   = config('services.ollama.model', 'llama3.2');
    }

    /**
     * Call Ollama chat endpoint with a system prompt and a user message.
     * Returns ['content' => array, 'tokens_used' => int].
     *
     * @throws RuntimeException on API error or non-JSON response
     */
    public function generate(string $systemPrompt, string $userMessage): array
    {
        $response = Http::timeout(180)->post("{$this->baseUrl}/api/chat", [
            'model'    => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userMessage],
            ],
            'stream' => false,
            'format' => 'json',
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Ollama API error '.$response->status().': '.$response->body()
            );
        }

        $data = $response->json();

        $text = $data['message']['content'] ?? null;

        if ($text === null) {
            throw new RuntimeException('Ollama returned no content: '.json_encode($data));
        }

        $parsed = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Ollama response is not valid JSON: '.$text);
        }

        $tokensUsed = ($data['prompt_eval_count'] ?? 0) + ($data['eval_count'] ?? 0);

        return [
            'content'     => $parsed,
            'tokens_used' => (int) $tokensUsed,
        ];
    }
}
