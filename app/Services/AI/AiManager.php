<?php

namespace App\Services\AI;

use RuntimeException;

class AiManager
{
    private AiProvider $provider;
    private string $providerName;
    private string $modelName;

    public function __construct()
    {
        $name = (string) config('services.ai.provider', 'ollama');
        $name = $name !== '' ? $name : 'ollama';

        $this->provider = $this->resolveProvider($name);
        $this->providerName = $name;
        $this->modelName = $this->resolveModelName($name);
    }

    public function generate(string $prompt, array $options = []): string
    {
        return $this->provider->generate($prompt, $options);
    }

    public function providerName(): string
    {
        return $this->providerName;
    }

    public function modelName(): string
    {
        return $this->modelName;
    }

    private function resolveProvider(string $name): AiProvider
    {
        return match ($name) {
            'gemini' => new GeminiProvider(),
            'groq'   => new GroqProvider(),
            'openai' => new OpenAiProvider(),
            'ollama' => new OllamaProvider(),
            default  => throw new RuntimeException("Provider de IA inválido: '{$name}'. Use: gemini, groq, openai, ollama."),
        };
    }

    private function resolveModelName(string $name): string
    {
        return match ($name) {
            'gemini' => config('services.ai.gemini.model', 'gemini-2.5-flash'),
            'groq'   => config('services.ai.groq.model', 'llama-3.3-70b-versatile'),
            'openai' => config('services.ai.openai.model', 'gpt-4.1-mini'),
            'ollama' => config('services.ai.ollama.model', 'qwen2.5:7b'),
            default  => 'unknown',
        };
    }
}
