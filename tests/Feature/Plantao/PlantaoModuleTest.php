<?php

use App\Models\PlantaoCqhServidor;
use App\Models\PlantaoCqhExterno;
use App\Models\PlantaoEquipe;
use App\Models\PlantaoEquipeServidor;
use App\Models\PlantaoEscala;
use App\Models\User;
use App\Services\Plantao\PlantaoCalendarService;
use App\Services\Plantao\PlantaoCqhService;
use App\Services\Plantao\PlantaoEquipeService;
use App\Services\Plantao\PlantaoEscalaService;
use App\Services\Plantao\PlantaoPdfService;
use App\Services\Plantao\PlantaoPermutaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (['ipc_plantao', 'epc_plantao', 'ipc', 'epc', 'cartorio_central', 'dpc'] as $role) {
        Role::findOrCreate($role);
    }
});

function plantaoUser(string $role, string $name = 'Servidor'): User
{
    $user = User::factory()->create(['name' => $name.' '.fake()->unique()->numberBetween(1, 999)]);
    $user->assignRole($role);

    return $user;
}

function equipeValida(): PlantaoEquipe
{
    $equipe = PlantaoEquipe::query()->create(['nome' => 'Equipe A']);
    foreach ([plantaoUser('ipc_plantao', 'IPC A'), plantaoUser('ipc_plantao', 'IPC B')] as $user) {
        PlantaoEquipeServidor::query()->create(['equipe_id' => $equipe->id, 'user_id' => $user->id, 'funcao_plantao' => 'ipc_plantao']);
    }
    PlantaoEquipeServidor::query()->create(['equipe_id' => $equipe->id, 'user_id' => plantaoUser('epc_plantao', 'EPC')->id, 'funcao_plantao' => 'epc_plantao']);

    return $equipe;
}

it('equipe aceita exatamente dois ipc plantao e um epc plantao', function (): void {
    app(PlantaoEquipeService::class)->validarEquipe(equipeValida());
    expect(true)->toBeTrue();
});

it('bloqueia equipe incompleta', function (): void {
    $equipe = PlantaoEquipe::query()->create(['nome' => 'Incompleta']);
    PlantaoEquipeServidor::query()->create(['equipe_id' => $equipe->id, 'user_id' => plantaoUser('ipc_plantao')->id, 'funcao_plantao' => 'ipc_plantao']);

    app(PlantaoEquipeService::class)->validarEquipe($equipe);
})->throws(ValidationException::class);

it('bloqueia usuario sem role correta', function (): void {
    $equipe = equipeValida();
    $linha = $equipe->servidores()->where('funcao_plantao', 'epc_plantao')->first();
    $linha->forceFill(['user_id' => plantaoUser('ipc_plantao')->id])->save();

    app(PlantaoEquipeService::class)->validarEquipe($equipe);
})->throws(ValidationException::class);

it('gera escala mensal 24x72 para todos os dias do mes', function (): void {
    $equipe = equipeValida();
    $summary = app(PlantaoEscalaService::class)->gerarEscalaMensal(5, 2026, $equipe->id);

    expect($summary['criados'])->toBe(31)
        ->and(PlantaoEscala::query()->whereYear('data_plantao', 2026)->whereMonth('data_plantao', 5)->count())->toBe(31);
});

it('permite permuta ipc mesmo com substituto de outra funcao enquanto bloqueios estao desativados', function (): void {
    $equipe = equipeValida();
    app(PlantaoEscalaService::class)->gerarEscalaMensal(5, 2026, $equipe->id);
    $escala = PlantaoEscala::query()->first();
    $ipcOriginal = $equipe->servidores()->where('funcao_plantao', 'ipc_plantao')->first()->user;
    $ipcSubstituto = $equipe->servidores()->where('funcao_plantao', 'ipc_plantao')->latest('id')->first()->user;
    $epcSubstituto = plantaoUser('epc_plantao', 'EPC Sub');

    expect(app(PlantaoPermutaService::class)->permutar($escala->id, $ipcOriginal->id, $ipcSubstituto->id, 'ipc_plantao'))->not->toBeNull();
    $membros = app(PlantaoCalendarService::class)->membrosFinais($escala->refresh());

    expect($membros['ipc'][0]->id)->toBe($ipcSubstituto->id)
        ->and($membros['ipc'][1]->id)->toBe($ipcOriginal->id);

    expect(app(PlantaoPermutaService::class)->permutar($escala->id, $ipcOriginal->id, $epcSubstituto->id, 'ipc_plantao'))->not->toBeNull();
});

it('gera cqh com servidores internos e externos derf e exibe derf no pdf', function (): void {
    $equipe = equipeValida();
    app(PlantaoEscalaService::class)->gerarEscalaMensal(5, 2026, $equipe->id);
    PlantaoCqhExterno::query()->create(['nome' => 'MARCELO DERF', 'unidade_operacional' => 'DERF_CONFRESA', 'ordem' => 1]);
    PlantaoCqhServidor::query()->create(['user_id' => plantaoUser('ipc', 'JOAO')->id, 'unidade_operacional' => 'CONFRESA', 'ordem' => 2]);

    expect(app(PlantaoCqhService::class)->gerarEscalaCqhMensal(5, 2026))->toBe(31);
    $dados = app(PlantaoPdfService::class)->dadosMensais(5, 2026);

    expect(collect($dados['linhas'])->pluck('cqh')->contains(fn ($name) => str_contains($name, '(DERF)')))->toBeTrue();
});

it('bloqueia pdf quando escala cqh nao foi gerada', function (): void {
    $equipe = equipeValida();
    app(PlantaoEscalaService::class)->gerarEscalaMensal(5, 2026, $equipe->id);

    app(PlantaoPdfService::class)->dadosMensais(5, 2026);
})->throws(ValidationException::class);

it('usa usuario com role dpc como delegado assinante do pdf', function (): void {
    plantaoUser('dpc', 'Delegado Teste');
    $equipe = equipeValida();
    app(PlantaoEscalaService::class)->gerarEscalaMensal(5, 2026, $equipe->id);
    PlantaoCqhServidor::query()->create(['user_id' => plantaoUser('ipc', 'JOAO')->id, 'unidade_operacional' => 'CONFRESA', 'ordem' => 1]);
    app(PlantaoCqhService::class)->gerarEscalaCqhMensal(5, 2026);

    expect(app(PlantaoPdfService::class)->dadosMensais(5, 2026)['delegado_nome'])->toContain('Delegado Teste');
});

it('permite permuta cqh entre servidor interno e externo', function (): void {
    $equipe = equipeValida();
    app(PlantaoEscalaService::class)->gerarEscalaMensal(5, 2026, $equipe->id);
    $interno = plantaoUser('ipc', 'JOAO INTERNO');
    $externo = PlantaoCqhExterno::query()->create(['nome' => 'MARCELO DERF', 'unidade_operacional' => 'DERF_CONFRESA', 'ordem' => 1]);
    PlantaoCqhServidor::query()->create(['user_id' => $interno->id, 'unidade_operacional' => 'CONFRESA', 'ordem' => 2]);
    $escala = PlantaoEscala::query()->first();

    app(PlantaoCqhService::class)->setCqhForDay($escala, $interno->id);
    $permuta = app(PlantaoPermutaService::class)->permutar($escala->id, 'user:'.$interno->id, 'externo:'.$externo->id, 'cqh_geral');

    $escala->refresh();
    expect($permuta->servidor_substituto_type)->toBe(PlantaoCqhExterno::class)
        ->and($escala->cqh_geral_type)->toBe(PlantaoCqhExterno::class)
        ->and($escala->cqh_geral_id)->toBe($externo->id);
});

it('permuta plantonistas entre dias diferentes atualizando origem e destino', function (): void {
    $equipeOrigem = equipeValida();
    $equipeDestino = equipeValida();
    app(PlantaoEscalaService::class)->gerarEscalaMensal(5, 2026, $equipeOrigem->id);

    $origem = PlantaoEscala::query()->whereDate('data_plantao', '2026-05-01')->first();
    $destino = PlantaoEscala::query()->whereDate('data_plantao', '2026-05-02')->first();
    expect($origem->equipe_id)->toBe($equipeOrigem->id)
        ->and($destino->equipe_id)->toBe($equipeDestino->id);

    $servidorOrigem = app(PlantaoCalendarService::class)->membrosFinais($origem)['ipc'][0];
    $servidorDestino = app(PlantaoCalendarService::class)->membrosFinais($destino)['ipc'][0];

    app(PlantaoPermutaService::class)->permutarEntreDias(
        $origem->id,
        $destino->id,
        'user:'.$servidorOrigem->id,
        'user:'.$servidorDestino->id,
        'ipc_plantao',
    );

    $membrosOrigem = app(PlantaoCalendarService::class)->membrosFinais($origem->refresh());
    $membrosDestino = app(PlantaoCalendarService::class)->membrosFinais($destino->refresh());

    expect($membrosOrigem['ipc'][0]->id)->toBe($servidorDestino->id)
        ->and($membrosDestino['ipc'][0]->id)->toBe($servidorOrigem->id);
});

it('fullcalendar retorna eventos corretos', function (): void {
    $equipe = equipeValida();
    app(PlantaoEscalaService::class)->gerarEscalaMensal(5, 2026, $equipe->id);

    expect(app(PlantaoCalendarService::class)->eventos())->toHaveCount(31);
});
