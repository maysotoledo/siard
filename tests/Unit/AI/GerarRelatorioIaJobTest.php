<?php

use App\Jobs\GerarRelatorioIaJob;
use App\Models\AiReport;
use App\Models\InvestigationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('muda status de pending para processing e depois para done', function () {
    $context = InvestigationContext::factory()->create([
        'texto_extraido' => 'Texto do BO para teste.',
    ]);

    $report = AiReport::factory()->create([
        'investigation_context_id' => $context->id,
        'tipo'                     => 'resumo_tecnico',
        'status'                   => 'pending',
        'prompt'                   => '',
    ]);

    // Mock AiManager to avoid real HTTP calls
    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response(['response' => 'Resumo gerado.'], 200),
    ]);

    config([
        'services.ai.provider'      => 'ollama',
        'services.ai.ollama.url'    => 'http://localhost:11434',
        'services.ai.ollama.model'  => 'test-model',
    ]);

    (new GerarRelatorioIaJob($report->id))->handle();

    $report->refresh();

    expect($report->status)->toBe('done');
    expect($report->resposta)->not->toBeNull();
});

it('muda status para failed quando ocorre erro', function () {
    $context = InvestigationContext::factory()->create([
        'texto_extraido' => 'Texto do BO.',
    ]);

    $report = AiReport::factory()->create([
        'investigation_context_id' => $context->id,
        'tipo'                     => 'resumo_tecnico',
        'status'                   => 'pending',
        'prompt'                   => '',
    ]);

    // Provider inválido força exceção
    config(['services.ai.provider' => 'provider-invalido']);

    (new GerarRelatorioIaJob($report->id))->handle();

    $report->refresh();

    expect($report->status)->toBe('failed');
    expect($report->erro)->not->toBeNull();
});
