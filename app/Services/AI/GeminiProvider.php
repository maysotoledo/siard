<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GeminiProvider implements AiProvider
{
    private string $key;
    private string $model;
    private int $timeout;

    public function __construct()
    {
        $this->key     = config('services.ai.gemini.key', '');
        $this->model   = config('services.ai.gemini.model', 'gemini-2.5-flash');
        $this->timeout = (int) config('services.ai.timeout', 180);

        if ($this->key === '') {
            throw new RuntimeException('GEMINI_API_KEY não configurada.');
        }
    }

    public function generate(string $prompt, array $options = []): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->key}";

        try {
            $response = Http::timeout($this->timeout)
                ->post($url, [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'temperature'     => 0.2,
                        'maxOutputTokens' => 8192,
                    ],
                ]);

            if ($response->failed()) {
                Log::error('GeminiProvider: request failed', ['status' => $response->status()]);
                throw new RuntimeException("Gemini retornou status {$response->status()}");
            }

            $text = $response->json('candidates.0.content.parts.0.text');

            if (! is_string($text) || trim($text) === '') {
                throw new RuntimeException('Gemini não retornou conteúdo.');
            }

            return trim($text);
        } catch (Throwable $e) {
            Log::error('GeminiProvider: ' . $e->getMessage());
            throw $e;
        }
    }
}
