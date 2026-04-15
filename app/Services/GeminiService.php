<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiService
{
    public function raw(string $prompt): string
    {
        $model = config('services.gemini.model');
        $key = config('services.gemini.key');

        $resp = Http::timeout(60)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}",
            ['contents' => [['parts' => [['text' => $prompt]]]]]
        )->json();

        return $resp['candidates'][0]['content']['parts'][0]['text'] ?? '<p>Report generation failed.</p>';
    }

    public function narrate(array $metrics): string
    {
        return $this->raw("Write a brief weekly HTML report from: " . json_encode($metrics));
    }
}
