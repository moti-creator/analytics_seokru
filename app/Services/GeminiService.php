<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    public function raw(string $prompt): string
    {
        // Prefer Groq (faster, higher free tier). Fall back to Gemini if no key.
        $groq = new GroqService();
        if ($groq->available()) {
            return $groq->raw($prompt);
        }

        $model = config('services.gemini.model');
        $key = config('services.gemini.key');

        $resp = Http::timeout(60)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}",
            ['contents' => [['parts' => [['text' => $prompt]]]]]
        );

        $json = $resp->json();
        return $json['candidates'][0]['content']['parts'][0]['text'] ?? '<p>Report generation failed.</p>';
    }

    public function narrate(array $metrics): string
    {
        return $this->raw("Write a brief weekly HTML report from: " . json_encode($metrics));
    }
}
