<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Groq API wrapper (OpenAI-compatible). Used as fallback when Gemini hits rate limits.
 * Supports both simple text generation and function-calling (tool use).
 */
class GroqService
{
    protected string $key;
    protected string $model;

    public function __construct()
    {
        $this->key = config('services.groq.key') ?? '';
        $this->model = config('services.groq.model') ?? 'llama-3.3-70b-versatile';
    }

    public function available(): bool
    {
        return $this->key !== '';
    }

    /**
     * Simple text completion — prompt in, text out.
     */
    public function raw(string $prompt): string
    {
        $resp = $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ]);
        return $resp['choices'][0]['message']['content'] ?? '<p>Groq fallback failed.</p>';
    }

    /**
     * Chat completion with optional tools (function calling).
     * Returns full response body.
     */
    public function chat(array $messages, ?array $tools = null, float $temperature = 0.3): array
    {
        $body = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => 4096,
        ];
        if ($tools) {
            $body['tools'] = $tools;
            $body['tool_choice'] = 'auto';
        }

        $resp = Http::timeout(90)
            ->withHeaders([
                'Authorization' => "Bearer {$this->key}",
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.groq.com/openai/v1/chat/completions', $body);

        if (!$resp->ok()) {
            Log::warning('Groq API error', ['status' => $resp->status(), 'body' => $resp->body()]);
            return ['choices' => [['message' => ['content' => '<p>LLM error (Groq): ' . e($resp->body()) . '</p>']]]];
        }

        return $resp->json();
    }

    /**
     * Convert Gemini-style function declarations to OpenAI-style tool definitions.
     */
    public static function convertToolDefs(array $geminiDefs): array
    {
        return array_map(fn($def) => [
            'type' => 'function',
            'function' => [
                'name' => $def['name'],
                'description' => $def['description'] ?? '',
                'parameters' => $def['parameters'] ?? ['type' => 'object', 'properties' => (object)[]],
            ],
        ], $geminiDefs);
    }
}
