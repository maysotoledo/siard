<?php

use App\Models\PlantaoCqhExterno;
use App\Models\PlantaoCqhServidor;
use App\Models\PlantaoDelegadoEscala;
use App\Models\PlantaoEquipe;
use App\Models\PlantaoEquipeServidor;
use App\Models\PlantaoEscala;
use App\Models\PlantaoHistorico;
use App\Models\User;
use App\Services\Plantao\PlantaoCalendarService;
use App\Services\Plantao\PlantaoCqhService;
use App\Services\Plantao\PlantaoDeltaImportService;
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

it('nao inclui servidores inativos ou nao aptos na escala cqh geral', function (): void {
    $equipe = equipeValida();
    app(PlantaoEscalaService::class)->gerarEscalaMensal(5, 2026, $equipe->id);

    $apto = plantaoUser('ipc', 'CQH APTO');
    $inativo = plantaoUser('ipc', 'CQH INATIVO');
    $naoApto = plantaoUser('ipc', 'CQH NAO APTO');

    PlantaoCqhServidor::query()->create(['user_id' => $apto->id, 'ativo' => true, 'apto_cqh' => true]);
    PlantaoCqhServidor::query()->create(['user_id' => $inativo->id, 'ativo' => false, 'apto_cqh' => true]);
    PlantaoCqhServidor::query()->create(['user_id' => $naoApto->id, 'ativo' => true, 'apto_cqh' => false]);
    PlantaoCqhExterno::query()->create(['nome' => 'EXTERNO INATIVO', 'ativo' => false, 'apto_cqh' => true]);
    PlantaoCqhExterno::query()->create(['nome' => 'EXTERNO NAO APTO', 'ativo' => true, 'apto_cqh' => false]);

    expect(app(PlantaoCqhService::class)->gerarEscalaCqhMensal(5, 2026))->toBe(31);

    $cqhGerais = PlantaoEscala::query()
        ->whereYear('data_plantao', 2026)
        ->whereMonth('data_plantao', 5)
        ->get(['cqh_geral_type', 'cqh_geral_id']);

    expect($cqhGerais->pluck('cqh_geral_type')->unique()->all())->toBe([User::class])
        ->and($cqhGerais->pluck('cqh_geral_id')->unique()->all())->toBe([$apto->id]);
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

it('detecta mes e ano da escala delta', function (): void {
    $detectado = app(PlantaoDeltaImportService::class)->detectarMesAno('◄ Abr 2026 Maio 2026 Jun 2026 ►');

    expect($detectado)->toMatchArray(['mes' => 5, 'ano' => 2026]);
});

it('extrai escala delta dos delegados com unidade contato horario e regionalizado', function (): void {
    $texto = <<<'TEXT'
Maio 2026
1
DR. ROGÉRIO
IRLANDES (DP
CONFRESA)
REGIONALIZADO
Contato: (67) 9201-0207
(Horário: Início às 08h de sexta até segunda às 08h.))
2
DRA. MARCELLA
MORISCO – Delegada de
Porto Alegre do Norte
Contato: (67) 9971-3236
TEXT;

    $registros = app(PlantaoDeltaImportService::class)->extrairRegistros($texto, 5, 2026);

    expect($registros)->toHaveCount(2)
        ->and($registros[0]['data'])->toBe('2026-05-01')
        ->and($registros[0]['nome'])->toBe('DR. ROGÉRIO IRLANDES')
        ->and($registros[0]['unidade'])->toBe('DP CONFRESA')
        ->and($registros[0]['contato'])->toBe('(67) 9201-0207')
        ->and($registros[0]['horario'])->toBe('Início às 08h de sexta até segunda às 08h.')
        ->and($registros[0]['regionalizado'])->toBeTrue()
        ->and($registros[1]['unidade'])->toBe('DP PORTO ALEGRE DO NORTE');
});

it('salva escala delta sem duplicar e sobrescreve somente com confirmacao', function (): void {
    $dados = [
        [
            'data_plantao' => '2026-05-01',
            'nome_delegado' => 'DR. ROGÉRIO IRLANDES',
            'unidade_delegado' => 'DP CONFRESA',
            'contato' => '(67) 9201-0207',
            'horario' => 'Início às 08h',
            'regionalizado' => true,
        ],
    ];

    $service = app(PlantaoDeltaImportService::class);
    $primeiro = $service->salvarEscalaDelegados($dados, false, 'maio.pdf');
    $segundo = $service->salvarEscalaDelegados([
        [
            ...$dados[0],
            'nome_delegado' => 'DRA. MARCELLA MORISCO',
        ],
    ], false, 'maio-v2.pdf');

    expect($primeiro['importados'])->toBe(1)
        ->and($segundo['ignorados'])->toBe(1)
        ->and(PlantaoDelegadoEscala::query()->count())->toBe(1)
        ->and(PlantaoDelegadoEscala::query()->first()->nome_delegado)->toBe('DR. ROGÉRIO IRLANDES');

    $terceiro = $service->salvarEscalaDelegados([
        [
            ...$dados[0],
            'nome_delegado' => 'DRA. MARCELLA MORISCO',
        ],
    ], true, 'maio-v2.pdf');

    expect($terceiro['sobrescritos'])->toBe(1)
        ->and(PlantaoDelegadoEscala::query()->first()->nome_delegado)->toBe('DRA. MARCELLA MORISCO')
        ->and(PlantaoHistorico::query()->where('acao', 'sobrescrever_escala_delta')->exists())->toBeTrue();
});

it('fullcalendar exibe dpc e contato da tabela delta', function (): void {
    $equipe = equipeValida();
    app(PlantaoEscalaService::class)->gerarEscalaMensal(5, 2026, $equipe->id);
    PlantaoDelegadoEscala::query()->create([
        'data_plantao' => '2026-05-01',
        'nome_delegado' => 'DR. ROGÉRIO IRLANDES',
        'contato' => '(67) 9201-0207',
    ]);

    $evento = collect(app(PlantaoCalendarService::class)->eventos())
        ->firstWhere('start', '2026-05-01');

    expect($evento['extendedProps']['dpc'])->toBe([
        'original' => 'DR. ROGÉRIO IRLANDES',
        'atual' => 'DR. ROGÉRIO IRLANDES',
        'permutado' => false,
    ])
        ->and($evento['extendedProps']['dpcContato'])->toBe('(67) 9201-0207');
});

it('pdf mensal inclui dpc e contato da escala delta', function (): void {
    plantaoUser('dpc', 'Delegado Teste');
    $equipe = equipeValida();
    app(PlantaoEscalaService::class)->gerarEscalaMensal(5, 2026, $equipe->id);
    PlantaoCqhServidor::query()->create(['user_id' => plantaoUser('ipc', 'JOAO')->id, 'unidade_operacional' => 'CONFRESA', 'ordem' => 1]);
    app(PlantaoCqhService::class)->gerarEscalaCqhMensal(5, 2026);
    PlantaoDelegadoEscala::query()->create([
        'data_plantao' => '2026-05-01',
        'nome_delegado' => 'DR. ROGÉRIO IRLANDES',
        'contato' => '(67) 9201-0207',
    ]);

    $linha = collect(app(PlantaoPdfService::class)->dadosMensais(5, 2026)['linhas'])
        ->firstWhere('dia', '01');

    expect($linha['dpc'])->toBe('DR. ROGÉRIO IRLANDES')
        ->and($linha['dpc_contato'])->toBe('(67) 9201-0207');
});
