<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class OpenAiProvider implements AiProvider
{
    private string $key;
    private string $model;
    private int $timeout;

    public function __construct()
    {
        $this->key     = config('services.ai.openai.key', '');
        $this->model   = config('services.ai.openai.model', 'gpt-4.1-mini');
        $this->timeout = (int) config('services.ai.timeout', 180);

        if ($this->key === '') {
            throw new RuntimeException('OPENAI_API_KEY não configurada.');
        }
    }

    public function generate(string $prompt, array $options = []): string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->key)
                ->post('https://api.openai.com/v1/responses', [
                    'model'       => $this->model,
                    'input'       => $prompt,
                    'temperature' => 0.2,
                ]);

            if ($response->failed()) {
                Log::error('OpenAiProvider: request failed', ['status' => $response->status()]);
                throw new RuntimeException("OpenAI retornou status {$response->status()}");
            }

            // Responses API: output_text helper or output[0].content[0].text
            $text = $response->json('output_text')
                ?? $response->json('output.0.content.0.text');

            if (! is_string($text) || trim($text) === '') {
                throw new RuntimeException('OpenAI não retornou conteúdo.');
            }

            return trim($text);
        } catch (Throwable $e) {
            Log::error('OpenAiProvider: ' . $e->getMessage());
            throw $e;
        }
    }
}
