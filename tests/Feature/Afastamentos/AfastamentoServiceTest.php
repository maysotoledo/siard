<?php

use App\Enums\StatusAfastamento;
use App\Enums\StatusPeriodoAquisitivo;
use App\Enums\TipoAfastamento;
use App\Enums\FuncaoOperacional;
use App\Models\AfastamentoCoberturaPlantao;
use App\Models\AfastamentoPeriodoAquisitivo;
use App\Models\AfastamentoPeriodoBloqueado;
use App\Models\AfastamentoRegra;
use App\Models\AfastamentoRegraOperacional;
use App\Models\AfastamentoSolicitacao;
use App\Models\User;
use App\Services\Afastamentos\AfastamentoConflictService;
use App\Services\Afastamentos\AfastamentoPeriodoAquisitivoService;
use App\Services\Afastamentos\AfastamentoSaldoService;
use App\Services\Afastamentos\AfastamentoService;
use App\Services\Afastamentos\AfastamentoSuggestionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-05-03 08:00:00');

    Role::findOrCreate('super_admin');
    Role::findOrCreate('ipc');
    Role::findOrCreate('ipc_plantao');
    Role::findOrCreate('epc');
    Role::findOrCreate('epc_plantao');
    Role::findOrCreate('cartorio_central');
    Role::findOrCreate('dpc');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    $this->actingAs($this->admin);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function periodo(User $user, TipoAfastamento $tipo, int $dias = 30): AfastamentoPeriodoAquisitivo
{
    return AfastamentoPeriodoAquisitivo::query()->create([
        'user_id' => $user->id,
        'tipo_afastamento' => $tipo->value,
        'data_inicio' => '2025-01-01',
        'data_fim' => '2025-12-31',
        'data_aquisicao' => '2026-01-01',
        'dias_direito' => $dias,
        'dias_usufruidos' => 0,
        'dias_disponiveis' => $dias,
        'status' => StatusPeriodoAquisitivo::ADQUIRIDO->value,
    ]);
}

function servidorComRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function solicitacaoOperacional(User $user, string $inicio = '2026-08-01', string $fim = '2026-08-10'): AfastamentoSolicitacao
{
    $solicitacao = AfastamentoSolicitacao::query()->make([
        'user_id' => $user->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => $inicio,
        'data_fim' => $fim,
        'dias_solicitados' => 10,
        'status' => StatusAfastamento::EM_ANALISE->value,
    ]);
    $solicitacao->setRelation('user', $user);

    return $solicitacao;
}

it('calcula saldo separado de ferias e licenca premio', function (): void {
    $user = User::factory()->create();
    periodo($user, TipoAfastamento::FERIAS, 30);
    periodo($user, TipoAfastamento::LICENCA_PREMIO, 90);

    expect(app(AfastamentoSaldoService::class)->saldoDisponivel($user, TipoAfastamento::FERIAS))->toBe(30)
        ->and(app(AfastamentoSaldoService::class)->saldoDisponivel($user, TipoAfastamento::LICENCA_PREMIO))->toBe(90);
});

it('gera ferias para servidor com tres anos de exercicio', function (): void {
    $user = User::factory()->create(['data_ingresso' => '2023-01-01']);

    $summary = app(AfastamentoPeriodoAquisitivoService::class)->gerarParaServidor($user, TipoAfastamento::FERIAS);

    expect($summary['criados'])->toBeGreaterThanOrEqual(3)
        ->and(AfastamentoPeriodoAquisitivo::query()
            ->where('user_id', $user->id)
            ->where('tipo_afastamento', TipoAfastamento::FERIAS->value)
            ->where('status', StatusPeriodoAquisitivo::ADQUIRIDO->value)
            ->count())->toBe(3);
});

it('gera licenca premio para servidor com seis anos de exercicio', function (): void {
    $user = User::factory()->create(['data_ingresso' => '2020-01-01']);

    $summary = app(AfastamentoPeriodoAquisitivoService::class)->gerarParaServidor($user, TipoAfastamento::LICENCA_PREMIO);

    expect($summary['criados'])->toBeGreaterThanOrEqual(1)
        ->and(AfastamentoPeriodoAquisitivo::query()
            ->where('user_id', $user->id)
            ->where('tipo_afastamento', TipoAfastamento::LICENCA_PREMIO->value)
            ->where('status', StatusPeriodoAquisitivo::ADQUIRIDO->value)
            ->count())->toBe(1);
});

it('nao duplica periodos ao executar duas vezes', function (): void {
    $user = User::factory()->create(['data_ingresso' => '2024-01-01']);
    $service = app(AfastamentoPeriodoAquisitivoService::class);

    $service->gerarParaServidor($user, TipoAfastamento::FERIAS);
    $total = AfastamentoPeriodoAquisitivo::query()->where('user_id', $user->id)->count();
    $summary = $service->gerarParaServidor($user, TipoAfastamento::FERIAS);

    expect(AfastamentoPeriodoAquisitivo::query()->where('user_id', $user->id)->count())->toBe($total)
        ->and($summary['criados'])->toBe(0);
});

it('recalcula saldo apos solicitacao aprovada', function (): void {
    $user = User::factory()->create();
    $periodo = periodo($user, TipoAfastamento::FERIAS, 30);

    AfastamentoSolicitacao::query()->create([
        'user_id' => $user->id,
        'periodo_aquisitivo_id' => $periodo->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => '2026-08-01',
        'data_fim' => '2026-08-10',
        'dias_solicitados' => 10,
        'dias_aprovados' => 10,
        'status' => StatusAfastamento::APROVADO->value,
    ]);

    app(AfastamentoPeriodoAquisitivoService::class)->recalcular($periodo);

    expect($periodo->refresh()->dias_usufruidos)->toBe(10)
        ->and($periodo->dias_disponiveis)->toBe(20)
        ->and($periodo->status)->toBe(StatusPeriodoAquisitivo::PARCIALMENTE_USUFRUIDO);
});

it('dry run nao salva periodos', function (): void {
    $user = User::factory()->create(['data_ingresso' => '2024-01-01']);

    $summary = app(AfastamentoPeriodoAquisitivoService::class)->gerarParaServidor($user, TipoAfastamento::FERIAS, dryRun: true);

    expect($summary['criados'])->toBeGreaterThan(0)
        ->and(AfastamentoPeriodoAquisitivo::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('servidor sem data de ingresso gera aviso e nao quebra', function (): void {
    $user = User::factory()->create(['data_ingresso' => null]);

    $summary = app(AfastamentoPeriodoAquisitivoService::class)->gerarParaServidor($user);

    expect($summary['ignorados'])->toBe(1)
        ->and($summary['avisos'])->not->toBeEmpty();
});

it('bloqueia saldo insuficiente', function (): void {
    $user = User::factory()->create();
    $pa = periodo($user, TipoAfastamento::FERIAS, 10);

    $solicitacao = AfastamentoSolicitacao::query()->create([
        'user_id' => $user->id,
        'periodo_aquisitivo_id' => $pa->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => now()->addMonth()->toDateString(),
        'data_fim' => now()->addMonth()->addDays(14)->toDateString(),
        'dias_solicitados' => 15,
        'status' => StatusAfastamento::EM_ANALISE->value,
    ]);

    app(AfastamentoSaldoService::class)->validarSaldo($solicitacao);
})->throws(ValidationException::class);

it('bloqueia criacao quando usuario nao tem direito ao tipo de afastamento', function (): void {
    $user = User::factory()->create();

    app(AfastamentoService::class)->salvar([
        'user_id' => $user->id,
        'tipo_afastamento' => TipoAfastamento::LICENCA_PREMIO->value,
        'data_inicio' => now()->addMonth()->toDateString(),
        'data_fim' => now()->addMonth()->addDays(9)->toDateString(),
        'status' => StatusAfastamento::RASCUNHO->value,
    ]);
})->throws(ValidationException::class, 'Selecione um período aquisitivo adquirido com saldo disponível para solicitar este afastamento.');

it('bloqueia criacao sem selecionar periodo aquisitivo mesmo com saldo existente', function (): void {
    $user = User::factory()->create();
    periodo($user, TipoAfastamento::FERIAS, 30);

    app(AfastamentoService::class)->salvar([
        'user_id' => $user->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => now()->addMonth()->toDateString(),
        'data_fim' => now()->addMonth()->addDays(9)->toDateString(),
        'status' => StatusAfastamento::RASCUNHO->value,
    ]);
})->throws(ValidationException::class, 'Selecione um período aquisitivo adquirido com saldo disponível para solicitar este afastamento.');

it('bloqueia criacao antes do periodo aquisitivo estar adquirido', function (): void {
    $user = User::factory()->create(['data_ingresso' => '2026-01-01']);
    app(AfastamentoPeriodoAquisitivoService::class)->gerarParaServidor($user, TipoAfastamento::FERIAS);

    app(AfastamentoService::class)->salvar([
        'user_id' => $user->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => now()->addMonth()->toDateString(),
        'data_fim' => now()->addMonth()->addDays(9)->toDateString(),
        'status' => StatusAfastamento::RASCUNHO->value,
    ]);
})->throws(ValidationException::class, 'Selecione um período aquisitivo adquirido com saldo disponível para solicitar este afastamento.');

it('bloqueia afastamento com dias abaixo do minimo por parcela configurado', function (): void {
    $user = User::factory()->create();
    $periodo = periodo($user, TipoAfastamento::FERIAS, 30);

    app(AfastamentoService::class)->salvar([
        'user_id' => $user->id,
        'periodo_aquisitivo_id' => $periodo->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => now()->addMonth()->toDateString(),
        'data_fim' => now()->addMonth()->addDays(4)->toDateString(),
        'status' => StatusAfastamento::RASCUNHO->value,
    ]);
})->throws(ValidationException::class, 'Cada parcela deste tipo de afastamento deve ter no mínimo 10 dias.');

it('bloqueia afastamento acima dos dias por periodo configurado', function (): void {
    $user = User::factory()->create();
    $periodo = periodo($user, TipoAfastamento::FERIAS, 45);

    app(AfastamentoService::class)->salvar([
        'user_id' => $user->id,
        'periodo_aquisitivo_id' => $periodo->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => now()->addMonth()->toDateString(),
        'data_fim' => now()->addMonth()->addDays(34)->toDateString(),
        'status' => StatusAfastamento::RASCUNHO->value,
    ]);
})->throws(ValidationException::class, 'A quantidade de dias solicitada não pode ultrapassar 30 dias para este tipo de afastamento.');

it('bloqueia quando excede quantidade maxima de parcelas configurada', function (): void {
    $user = User::factory()->create();
    $periodo = periodo($user, TipoAfastamento::FERIAS, 30);

    foreach (range(1, 3) as $index) {
        AfastamentoSolicitacao::query()->create([
            'user_id' => $user->id,
            'periodo_aquisitivo_id' => $periodo->id,
            'tipo_afastamento' => TipoAfastamento::FERIAS->value,
            'data_inicio' => now()->addMonths($index)->toDateString(),
            'data_fim' => now()->addMonths($index)->addDays(9)->toDateString(),
            'dias_solicitados' => 10,
            'status' => StatusAfastamento::SOLICITADO->value,
        ]);
    }

    app(AfastamentoService::class)->salvar([
        'user_id' => $user->id,
        'periodo_aquisitivo_id' => $periodo->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => now()->addMonths(6)->toDateString(),
        'data_fim' => now()->addMonths(6)->addDays(9)->toDateString(),
        'status' => StatusAfastamento::RASCUNHO->value,
    ]);
})->throws(ValidationException::class, 'A quantidade máxima de parcelas para este tipo de afastamento é 3.');

it('bloqueia parcelamento quando regra nao permite', function (): void {
    AfastamentoRegra::query()
        ->where('tipo_afastamento', TipoAfastamento::FERIAS->value)
        ->update(['permite_parcelamento' => false]);

    $user = User::factory()->create();
    $periodo = periodo($user, TipoAfastamento::FERIAS, 30);

    app(AfastamentoService::class)->salvar([
        'user_id' => $user->id,
        'periodo_aquisitivo_id' => $periodo->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => now()->addMonth()->toDateString(),
        'data_fim' => now()->addMonth()->addDays(9)->toDateString(),
        'status' => StatusAfastamento::RASCUNHO->value,
    ]);
})->throws(ValidationException::class, 'A regra deste tipo de afastamento não permite parcelamento. Solicite o período integral de 30 dias.');

it('detecta conflito de datas do mesmo servidor', function (): void {
    $user = User::factory()->create();
    AfastamentoSolicitacao::query()->create([
        'user_id' => $user->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => '2026-08-01',
        'data_fim' => '2026-08-10',
        'dias_solicitados' => 10,
        'status' => StatusAfastamento::APROVADO->value,
    ]);

    $nova = AfastamentoSolicitacao::query()->make([
        'user_id' => $user->id,
        'tipo_afastamento' => TipoAfastamento::LICENCA_PREMIO->value,
        'data_inicio' => '2026-08-05',
        'data_fim' => '2026-08-12',
        'dias_solicitados' => 8,
        'status' => StatusAfastamento::EM_ANALISE->value,
    ]);
    $nova->setRelation('user', $user);

    expect(app(AfastamentoConflictService::class)->detectar($nova))->not->toBeEmpty();
});

it('detecta bloqueio por periodo bloqueado', function (): void {
    $user = User::factory()->create();
    AfastamentoPeriodoBloqueado::query()->create([
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => '2026-09-01',
        'data_fim' => '2026-09-30',
        'motivo' => 'Operação crítica',
        'ativo' => true,
    ]);

    $solicitacao = AfastamentoSolicitacao::query()->make([
        'user_id' => $user->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => '2026-09-10',
        'data_fim' => '2026-09-20',
        'dias_solicitados' => 11,
    ]);

    expect(app(AfastamentoConflictService::class)->possuiCritico($solicitacao))->toBeTrue();
});

it('detecta conflito com efetivo minimo por cargo', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userA->assignRole('ipc');
    $userB->assignRole('ipc');

    AfastamentoRegraOperacional::query()->create([
        'cargo' => 'ipc',
        'minimo_por_dia' => 1,
        'maximo_afastados_simultaneos' => 1,
        'ativo' => true,
    ]);

    AfastamentoSolicitacao::query()->create([
        'user_id' => $userA->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => '2026-10-01',
        'data_fim' => '2026-10-10',
        'dias_solicitados' => 10,
        'status' => StatusAfastamento::APROVADO->value,
    ]);

    $nova = AfastamentoSolicitacao::query()->make([
        'user_id' => $userB->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => '2026-10-03',
        'data_fim' => '2026-10-08',
        'dias_solicitados' => 6,
    ]);
    $nova->setRelation('user', $userB);

    expect(app(AfastamentoConflictService::class)->possuiCritico($nova))->toBeTrue();
});

it('mapeia funcao operacional separando expediente e plantao', function (): void {
    expect(servidorComRole('ipc')->funcao_operacional)->toBe(FuncaoOperacional::IPC_EXPEDIENTE)
        ->and(servidorComRole('ipc_plantao')->funcao_operacional)->toBe(FuncaoOperacional::IPC_PLANTAO)
        ->and(servidorComRole('epc')->funcao_operacional)->toBe(FuncaoOperacional::EPC_EXPEDIENTE)
        ->and(servidorComRole('epc_plantao')->funcao_operacional)->toBe(FuncaoOperacional::EPC_PLANTAO);
});

it('ipc expediente pode sair se ficarem pelo menos dois no expediente', function (): void {
    $alvo = servidorComRole('ipc');
    servidorComRole('ipc');
    servidorComRole('ipc');

    expect(app(AfastamentoConflictService::class)->possuiCritico(solicitacaoOperacional($alvo)))->toBeFalse();
});

it('ipc expediente nao pode sair se ficarem menos de dois no expediente', function (): void {
    $alvo = servidorComRole('ipc');
    servidorComRole('ipc');

    expect(app(AfastamentoConflictService::class)->possuiCritico(solicitacaoOperacional($alvo)))->toBeTrue();
});

it('bloqueia solicitacao desde a criacao quando ipc expediente viola minimo operacional', function (): void {
    $alvo = servidorComRole('ipc');
    servidorComRole('ipc');
    $periodo = periodo($alvo, TipoAfastamento::FERIAS, 30);

    app(AfastamentoService::class)->salvar([
        'user_id' => $alvo->id,
        'periodo_aquisitivo_id' => $periodo->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => '2026-08-01',
        'data_fim' => '2026-08-10',
        'status' => StatusAfastamento::RASCUNHO->value,
    ]);
})->throws(ValidationException::class, 'Afastamento deixará IPC expediente');

it('ipc plantao pode sair se houver ipc expediente disponivel para cobertura', function (): void {
    $plantao = servidorComRole('ipc_plantao');
    $cobertura = servidorComRole('ipc');
    servidorComRole('ipc');
    servidorComRole('ipc');

    $solicitacao = AfastamentoSolicitacao::query()->create([
        'user_id' => $plantao->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => '2026-08-01',
        'data_fim' => '2026-08-10',
        'dias_solicitados' => 10,
        'status' => StatusAfastamento::EM_ANALISE->value,
    ]);
    $solicitacao->setRelation('user', $plantao);

    AfastamentoCoberturaPlantao::query()->create([
        'afastamento_solicitacao_id' => $solicitacao->id,
        'servidor_plantao_afastado_id' => $plantao->id,
        'servidor_cobertura_id' => $cobertura->id,
        'funcao_origem' => FuncaoOperacional::IPC_EXPEDIENTE->value,
        'funcao_destino' => FuncaoOperacional::IPC_PLANTAO->value,
        'data_inicio' => '2026-08-01',
        'data_fim' => '2026-08-10',
        'status' => 'aprovada',
    ]);

    expect(app(AfastamentoConflictService::class)->possuiCritico($solicitacao))->toBeFalse();
});

it('ipc plantao nao pode sair se nao houver cobertura', function (): void {
    $plantao = servidorComRole('ipc_plantao');

    expect(app(AfastamentoConflictService::class)->possuiCritico(solicitacaoOperacional($plantao)))->toBeTrue();
});

it('bloqueia solicitacao desde a criacao quando ipc plantao nao tem cobertura possivel', function (): void {
    $plantao = servidorComRole('ipc_plantao');
    $periodo = periodo($plantao, TipoAfastamento::FERIAS, 30);

    app(AfastamentoService::class)->salvar([
        'user_id' => $plantao->id,
        'periodo_aquisitivo_id' => $periodo->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => '2026-08-01',
        'data_fim' => '2026-08-10',
        'status' => StatusAfastamento::RASCUNHO->value,
    ]);
})->throws(ValidationException::class, 'IPC plantão sem cobertura disponível.');

it('permite criar solicitacao de ipc plantao quando ha cobertura possivel', function (): void {
    $plantao = servidorComRole('ipc_plantao');
    servidorComRole('ipc');
    servidorComRole('ipc');
    servidorComRole('ipc');
    $periodo = periodo($plantao, TipoAfastamento::FERIAS, 30);

    $solicitacao = app(AfastamentoService::class)->salvar([
        'user_id' => $plantao->id,
        'periodo_aquisitivo_id' => $periodo->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => '2026-08-01',
        'data_fim' => '2026-08-10',
        'status' => StatusAfastamento::RASCUNHO->value,
    ]);

    expect($solicitacao)->toBeInstanceOf(AfastamentoSolicitacao::class);
});

it('ipc plantao nao pode sair se cobertura deixar expediente com menos de dois', function (): void {
    $plantao = servidorComRole('ipc_plantao');
    servidorComRole('ipc');
    servidorComRole('ipc');

    expect(app(AfastamentoConflictService::class)->possuiCritico(solicitacaoOperacional($plantao)))->toBeTrue();
});

it('ipc expediente em cobertura nao pode tirar afastamento no mesmo periodo', function (): void {
    $plantao = servidorComRole('ipc_plantao');
    $cobertura = servidorComRole('ipc');
    servidorComRole('ipc');
    servidorComRole('ipc');

    $solicitacaoPlantao = AfastamentoSolicitacao::query()->create([
        'user_id' => $plantao->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => '2026-08-01',
        'data_fim' => '2026-08-10',
        'dias_solicitados' => 10,
        'status' => StatusAfastamento::EM_ANALISE->value,
    ]);

    AfastamentoCoberturaPlantao::query()->create([
        'afastamento_solicitacao_id' => $solicitacaoPlantao->id,
        'servidor_plantao_afastado_id' => $plantao->id,
        'servidor_cobertura_id' => $cobertura->id,
        'funcao_origem' => FuncaoOperacional::IPC_EXPEDIENTE->value,
        'funcao_destino' => FuncaoOperacional::IPC_PLANTAO->value,
        'data_inicio' => '2026-08-01',
        'data_fim' => '2026-08-10',
        'status' => 'aprovada',
    ]);

    expect(app(AfastamentoConflictService::class)->possuiCritico(solicitacaoOperacional($cobertura)))->toBeTrue();
});

it('permite afastamento simultaneo de plantao e expediente se regras forem respeitadas', function (): void {
    $plantao = servidorComRole('ipc_plantao');
    $expedienteAfastado = servidorComRole('ipc');
    $cobertura = servidorComRole('ipc');
    servidorComRole('ipc');
    servidorComRole('ipc');

    AfastamentoSolicitacao::query()->create([
        'user_id' => $expedienteAfastado->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => '2026-08-01',
        'data_fim' => '2026-08-10',
        'dias_solicitados' => 10,
        'status' => StatusAfastamento::APROVADO->value,
    ]);

    $solicitacaoPlantao = AfastamentoSolicitacao::query()->create([
        'user_id' => $plantao->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => '2026-08-01',
        'data_fim' => '2026-08-10',
        'dias_solicitados' => 10,
        'status' => StatusAfastamento::EM_ANALISE->value,
    ]);
    $solicitacaoPlantao->setRelation('user', $plantao);

    AfastamentoCoberturaPlantao::query()->create([
        'afastamento_solicitacao_id' => $solicitacaoPlantao->id,
        'servidor_plantao_afastado_id' => $plantao->id,
        'servidor_cobertura_id' => $cobertura->id,
        'funcao_origem' => FuncaoOperacional::IPC_EXPEDIENTE->value,
        'funcao_destino' => FuncaoOperacional::IPC_PLANTAO->value,
        'data_inicio' => '2026-08-01',
        'data_fim' => '2026-08-10',
        'status' => 'aprovada',
    ]);

    expect(app(AfastamentoConflictService::class)->possuiCritico($solicitacaoPlantao))->toBeFalse();
});

it('aprova abatendo saldo e cancela devolvendo saldo', function (): void {
    $user = User::factory()->create();
    $pa = periodo($user, TipoAfastamento::FERIAS, 30);

    $solicitacao = AfastamentoSolicitacao::query()->create([
        'user_id' => $user->id,
        'periodo_aquisitivo_id' => $pa->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => now()->addMonths(2)->toDateString(),
        'data_fim' => now()->addMonths(2)->addDays(9)->toDateString(),
        'dias_solicitados' => 10,
        'status' => StatusAfastamento::EM_ANALISE->value,
    ]);

    app(AfastamentoService::class)->aprovar($solicitacao, 'Planejamento aprovado', true);
    expect($pa->refresh()->dias_disponiveis)->toBe(20);

    app(AfastamentoService::class)->cancelar($solicitacao->refresh(), 'Cancelamento administrativo');
    expect($pa->refresh()->dias_disponiveis)->toBe(30);
});

it('interrompe conforme regra do tipo devolvendo saldo quando configurado', function (): void {
    AfastamentoRegra::query()->where('tipo_afastamento', TipoAfastamento::FERIAS->value)->update([
        'permite_interrupcao' => true,
        'devolve_saldo_ao_interromper' => true,
    ]);

    $user = User::factory()->create();
    $pa = periodo($user, TipoAfastamento::FERIAS, 30);
    $solicitacao = AfastamentoSolicitacao::query()->create([
        'user_id' => $user->id,
        'periodo_aquisitivo_id' => $pa->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => now()->addMonths(3)->toDateString(),
        'data_fim' => now()->addMonths(3)->addDays(9)->toDateString(),
        'dias_solicitados' => 10,
        'dias_aprovados' => 10,
        'status' => StatusAfastamento::APROVADO->value,
    ]);
    app(AfastamentoSaldoService::class)->abater($solicitacao);

    app(AfastamentoService::class)->interromper($solicitacao, now()->addMonths(3)->addDays(5), 'Necessidade do serviço');

    expect($solicitacao->refresh()->status)->toBe(StatusAfastamento::INTERROMPIDO)
        ->and($pa->refresh()->dias_disponiveis)->toBeGreaterThan(20);
});

it('sugere periodos alternativos', function (): void {
    $user = User::factory()->create();
    $solicitacao = AfastamentoSolicitacao::query()->make([
        'user_id' => $user->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => now()->addMonth()->toDateString(),
        'data_fim' => now()->addMonth()->addDays(9)->toDateString(),
        'dias_solicitados' => 10,
    ]);
    $solicitacao->setRelation('user', $user);

    expect(app(AfastamentoSuggestionService::class)->sugerir($solicitacao))->toHaveCount(3);
});

it('nao trava ao sugerir datas quando todos os periodos seguem criticos', function (): void {
    $user = User::factory()->create();

    AfastamentoPeriodoBloqueado::query()->create([
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => '2026-01-01',
        'data_fim' => '2027-12-31',
        'motivo' => 'Bloqueio amplo',
        'ativo' => true,
    ]);

    $solicitacao = AfastamentoSolicitacao::query()->make([
        'user_id' => $user->id,
        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
        'data_inicio' => '2026-08-01',
        'data_fim' => '2026-08-10',
        'dias_solicitados' => 10,
    ]);
    $solicitacao->setRelation('user', $user);

    expect(app(AfastamentoSuggestionService::class)->sugerir($solicitacao))->toBeArray();
});
