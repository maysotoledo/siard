<?php

use App\Services\AI\AiManager;
use App\Services\AI\GeminiProvider;
use App\Services\AI\GroqProvider;
use App\Services\AI\OllamaProvider;
use App\Services\AI\OpenAiProvider;

it('selects ollama provider by default', function () {
    config(['services.ai.provider' => 'ollama']);

    $manager = new AiManager();

    expect($manager->providerName())->toBe('ollama');
});

it('selects gemini provider when configured', function () {
    config([
        'services.ai.provider'    => 'gemini',
        'services.ai.gemini.key'  => 'test-key',
        'services.ai.gemini.model' => 'gemini-2.5-flash',
    ]);

    $manager = new AiManager();

    expect($manager->providerName())->toBe('gemini');
    expect($manager->modelName())->toBe('gemini-2.5-flash');
});

it('selects groq provider when configured', function () {
    config([
        'services.ai.provider'   => 'groq',
        'services.ai.groq.key'   => 'test-key',
        'services.ai.groq.model' => 'llama-3.3-70b-versatile',
    ]);

    $manager = new AiManager();

    expect($manager->providerName())->toBe('groq');
});

it('selects openai provider when configured', function () {
    config([
        'services.ai.provider'    => 'openai',
        'services.ai.openai.key'  => 'test-key',
        'services.ai.openai.model' => 'gpt-4.1-mini',
    ]);

    $manager = new AiManager();

    expect($manager->providerName())->toBe('openai');
});

it('throws exception for invalid provider', function () {
    config(['services.ai.provider' => 'invalid-provider']);

    expect(fn () => new AiManager())
        ->toThrow(\RuntimeException::class, "Provider de IA inválido: 'invalid-provider'");
});

it('throws exception when gemini api key is missing', function () {
    config([
        'services.ai.provider'   => 'gemini',
        'services.ai.gemini.key' => '',
    ]);

    expect(fn () => new AiManager())
        ->toThrow(\RuntimeException::class, 'GEMINI_API_KEY não configurada');
});

it('throws exception when groq api key is missing', function () {
    config([
        'services.ai.provider'  => 'groq',
        'services.ai.groq.key'  => '',
    ]);

    expect(fn () => new AiManager())
        ->toThrow(\RuntimeException::class, 'GROQ_API_KEY não configurada');
});

it('throws exception when openai api key is missing', function () {
    config([
        'services.ai.provider'   => 'openai',
        'services.ai.openai.key' => '',
    ]);

    expect(fn () => new AiManager())
        ->toThrow(\RuntimeException::class, 'OPENAI_API_KEY não configurada');
});

it('falls back to ollama when provider is empty string', function () {
    config(['services.ai.provider' => '']);

    $manager = new AiManager();

    expect($manager->providerName())->toBe('ollama');
});
