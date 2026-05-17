<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GroqProvider implements AiProvider
{
    private string $key;
    private string $model;
    private int $timeout;

    public function __construct()
    {
        $this->key     = config('services.ai.groq.key', '');
        $this->model   = config('services.ai.groq.model', 'llama-3.3-70b-versatile');
        $this->timeout = (int) config('services.ai.timeout', 180);

        if ($this->key === '') {
            throw new RuntimeException('GROQ_API_KEY não configurada.');
        }
    }

    public function generate(string $prompt, array $options = []): string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->key)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model'       => $this->model,
                    'temperature' => 0.2,
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => 'Você é um analista técnico policial especializado em investigação, análise de logs, rastreamento digital e elaboração de relatório policial.',
                        ],
                        [
                            'role'    => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::error('GroqProvider: request failed', ['status' => $response->status()]);
                throw new RuntimeException("Groq retornou status {$response->status()}");
            }

            $text = $response->json('choices.0.message.content');

            if (! is_string($text) || trim($text) === '') {
                throw new RuntimeException('Groq não retornou conteúdo.');
            }

            return trim($text);
        } catch (Throwable $e) {
            Log::error('GroqProvider: ' . $e->getMessage());
            throw $e;
        }
    }
}
