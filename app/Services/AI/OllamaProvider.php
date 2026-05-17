<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class OllamaProvider implements AiProvider
{
    private string $url;
    private string $model;
    private int $timeout;

    public function __construct()
    {
        $this->url     = rtrim(config('services.ai.ollama.url', 'http://ollama:11434'), '/');
        $this->model   = config('services.ai.ollama.model', 'qwen2.5:7b');
        $this->timeout = (int) config('services.ai.timeout', 180);
    }

    public function generate(string $prompt, array $options = []): string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->url}/api/generate", [
                    'model'  => $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                ]);

            if ($response->failed()) {
                Log::error('OllamaProvider: request failed', ['status' => $response->status()]);
                throw new RuntimeException("Ollama retornou status {$response->status()}");
            }

            $text = $response->json('response');

            if (! is_string($text) || trim($text) === '') {
                throw new RuntimeException('Ollama não retornou conteúdo.');
            }

            return trim($text);
        } catch (Throwable $e) {
            Log::error('OllamaProvider: ' . $e->getMessage());
            throw $e;
        }
    }
}
