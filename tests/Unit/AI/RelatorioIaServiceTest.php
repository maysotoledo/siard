<?php

use App\Models\InvestigationContext;
use App\Services\AI\AiManager;
use App\Services\AI\RelatorioIaService;

beforeEach(function () {
    $this->manager = Mockery::mock(AiManager::class);
    $this->service = new RelatorioIaService($this->manager);
});

it('prompt do relatorio completo contém texto do BO', function () {
    $context = new InvestigationContext([
        'titulo'          => 'Teste',
        'numero_bo'       => '123/2026',
        'natureza'        => 'Furto',
        'texto_extraido'  => 'Vítima relata que teve seu celular furtado.',
        'vitimas'         => ['João Silva'],
        'suspeitos'       => [],
    ]);

    $this->manager->shouldReceive('generate')
        ->once()
        ->withArgs(function (string $prompt): bool {
            return str_contains($prompt, 'Vítima relata que teve seu celular furtado.')
                && str_contains($prompt, '123/2026')
                && str_contains($prompt, 'RELATÓRIO DE INVESTIGAÇÃO');
        })
        ->andReturn('Relatório gerado.');

    $result = $this->service->gerarRelatorioCompleto($context);

    expect($result)->toBe('Relatório gerado.');
});

it('prompt contém dados técnicos do SIARD quando run disponível', function () {
    $context = new InvestigationContext([
        'titulo'         => 'Teste',
        'texto_extraido' => 'Texto do BO.',
    ]);

    $run = Mockery::mock(\App\Models\AnaliseRun::class)->makePartial();
    $run->id              = 42;
    $run->target          = '@alvo';
    $run->status          = 'done';
    $run->total_unique_ips = 5;
    $run->report = [
        'timeline_rows'       => [
            ['datetime' => '2026-01-01 02:00:00', 'ip' => '1.2.3.4', 'provider' => 'Vivo', 'city' => 'SP', 'type' => 'Móvel'],
        ],
        'unique_ip_rows'      => [],
        'provider_stats_rows' => [],
        'city_stats_rows'     => [],
        'night_events_rows'   => [],
        'hourly_rows'         => [],
        'night_total_events'  => 1,
    ];

    $this->manager->shouldReceive('generate')
        ->once()
        ->withArgs(function (string $prompt): bool {
            return str_contains($prompt, 'DADOS TÉCNICOS DO SIARD')
                && str_contains($prompt, '1.2.3.4');
        })
        ->andReturn('Relatório com dados técnicos.');

    $result = $this->service->gerarRelatorioCompleto($context, $run);

    expect($result)->toBe('Relatório com dados técnicos.');
});

afterEach(fn () => Mockery::close());
