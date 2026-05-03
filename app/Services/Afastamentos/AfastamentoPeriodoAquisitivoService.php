<?php

namespace App\Services\Afastamentos;

use App\Enums\StatusAfastamento;
use App\Enums\StatusPeriodoAquisitivo;
use App\Enums\TipoAfastamento;
use App\Models\AfastamentoPeriodoAquisitivo;
use App\Models\AfastamentoRegra;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AfastamentoPeriodoAquisitivoService
{
    private const CAMPOS_DATA_INGRESSO = [
        'data_ingresso',
        'data_posse',
        'data_exercicio',
        'admitted_at',
    ];

    public function regra(TipoAfastamento|string $tipo): AfastamentoRegra
    {
        $tipoValue = $tipo instanceof TipoAfastamento ? $tipo->value : $tipo;
        $tipoEnum = TipoAfastamento::from($tipoValue);

        return AfastamentoRegra::query()
            ->ativa()
            ->where('tipo_afastamento', $tipoValue)
            ->orderByDesc('id')
            ->first()
            ?? AfastamentoRegra::query()->create([
                'tipo_afastamento' => $tipoValue,
                'nome' => $tipoEnum->label(),
                'dias_por_periodo' => $tipoEnum === TipoAfastamento::LICENCA_PREMIO ? 90 : 30,
                'meses_para_aquisicao' => $tipoEnum === TipoAfastamento::LICENCA_PREMIO ? 60 : 12,
                'ativo' => true,
            ]);
    }

    public function gerarParaServidor(User|Model $servidor, TipoAfastamento|string|null $tipo = null, bool $dryRun = false, bool $force = false): array
    {
        $summary = $this->emptySummary($dryRun);
        $dataIngresso = $this->dataIngresso($servidor);

        if (! $dataIngresso) {
            $summary['avisos'][] = sprintf('Servidor %s sem data de ingresso/posse/exercício.', $servidor->getKey());
            $summary['ignorados']++;

            return $summary;
        }

        foreach ($this->tipos($tipo) as $tipoEnum) {
            foreach ($this->calcularPeriodos($dataIngresso, $tipoEnum) as $periodoCalculado) {
                $resultado = $this->evitarDuplicidade($servidor, $periodoCalculado, $dryRun, $force);
                $summary[$resultado]++;
            }
        }

        return $summary;
    }

    public function gerarParaTodos(TipoAfastamento|string|null $tipo = null, bool $dryRun = false, bool $force = false): array
    {
        return DB::transaction(function () use ($tipo, $dryRun, $force): array {
            $summary = $this->emptySummary($dryRun);

            User::query()
                ->orderBy('id')
                ->chunkById(100, function ($servidores) use (&$summary, $tipo, $dryRun, $force): void {
                    foreach ($servidores as $servidor) {
                        $partial = $this->gerarParaServidor($servidor, $tipo, $dryRun, $force);
                        $summary = $this->mergeSummary($summary, $partial);
                    }
                });

            return $summary;
        });
    }

    public function recalcularParaServidor(User|Model $servidor, TipoAfastamento|string|null $tipo = null): array
    {
        $summary = $this->emptySummary(false);

        AfastamentoPeriodoAquisitivo::query()
            ->where('user_id', $servidor->getKey())
            ->when($tipo, fn ($query) => $query->where('tipo_afastamento', $tipo instanceof TipoAfastamento ? $tipo->value : $tipo))
            ->each(function (AfastamentoPeriodoAquisitivo $periodo) use (&$summary): void {
                $this->recalcular($periodo);
                $summary['atualizados']++;
            });

        return $summary;
    }

    public function calcularPeriodos(CarbonInterface|string $dataIngresso, TipoAfastamento|string $tipoAfastamento): array
    {
        $tipo = $tipoAfastamento instanceof TipoAfastamento ? $tipoAfastamento : TipoAfastamento::from($tipoAfastamento);
        $regra = $this->regra($tipo);
        $inicioBase = CarbonImmutable::parse($dataIngresso)->startOfDay();
        $hoje = CarbonImmutable::now()->startOfDay();
        $periodos = [];
        $inicio = $inicioBase;

        while ($inicio->lte($hoje)) {
            $fim = $inicio->addMonthsNoOverflow((int) $regra->meses_para_aquisicao)->subDay();
            $aquisicao = $fim->addDay();

            $periodos[] = [
                'tipo_afastamento' => $tipo->value,
                'data_inicio' => $inicio->toDateString(),
                'data_fim' => $fim->toDateString(),
                'data_aquisicao' => $aquisicao->toDateString(),
                'dias_direito' => (int) $regra->dias_por_periodo,
                'status' => $hoje->lt($aquisicao)
                    ? StatusPeriodoAquisitivo::EM_AQUISICAO->value
                    : StatusPeriodoAquisitivo::ADQUIRIDO->value,
            ];

            $inicio = $aquisicao;
        }

        return $periodos;
    }

    public function atualizarSaldos(User|Model|int $servidor, TipoAfastamento|string|null $tipo = null): array
    {
        $userId = $servidor instanceof Model ? $servidor->getKey() : $servidor;
        $summary = $this->emptySummary(false);

        AfastamentoPeriodoAquisitivo::query()
            ->where('user_id', $userId)
            ->when($tipo, fn ($query) => $query->where('tipo_afastamento', $tipo instanceof TipoAfastamento ? $tipo->value : $tipo))
            ->each(function (AfastamentoPeriodoAquisitivo $periodo) use (&$summary): void {
                $this->recalcular($periodo);
                $summary['atualizados']++;
            });

        return $summary;
    }

    public function evitarDuplicidade(User|Model $servidor, array $periodoCalculado, bool $dryRun = false, bool $force = false): string
    {
        $periodo = AfastamentoPeriodoAquisitivo::query()
            ->where('user_id', $servidor->getKey())
            ->where('tipo_afastamento', $periodoCalculado['tipo_afastamento'])
            ->whereDate('data_inicio', $periodoCalculado['data_inicio'])
            ->whereDate('data_fim', $periodoCalculado['data_fim'])
            ->first();

        if (! $periodo) {
            if (! $dryRun) {
                AfastamentoPeriodoAquisitivo::query()->create([
                    'user_id' => $servidor->getKey(),
                    ...$periodoCalculado,
                    'dias_usufruidos' => 0,
                    'dias_disponiveis' => $periodoCalculado['dias_direito'],
                    'gerado_automaticamente' => true,
                    'observacao' => 'Gerado automaticamente conforme regra de afastamento configurada.',
                ]);
            }

            return 'criados';
        }

        $temSolicitacoes = $periodo->solicitacoes()->exists();
        $podeAtualizarDadosGerais = $force || (bool) $periodo->gerado_automaticamente;

        if (! $podeAtualizarDadosGerais && ! $temSolicitacoes) {
            return 'ignorados';
        }

        if ($dryRun) {
            return 'atualizados';
        }

        if (! $temSolicitacoes && $podeAtualizarDadosGerais) {
            $periodo->forceFill([
                'data_aquisicao' => $periodoCalculado['data_aquisicao'],
                'dias_direito' => $periodoCalculado['dias_direito'],
                'gerado_automaticamente' => true,
            ])->save();
        }

        $this->recalcular($periodo->refresh());

        return 'atualizados';
    }

    public function gerarParaUsuario(User $user, TipoAfastamento|string $tipo, ?Carbon $dataBase = null, int $quantidade = 1): array
    {
        if ($dataBase) {
            $user->forceFill(['data_ingresso' => $dataBase->toDateString()]);
        }

        return $this->gerarParaServidor($user, $tipo);
    }

    public function recalcular(AfastamentoPeriodoAquisitivo $periodo): AfastamentoPeriodoAquisitivo
    {
        $usados = $periodo->solicitacoes()
            ->with('interrupcoes')
            ->whereIn('status', [
                StatusAfastamento::APROVADO->value,
                StatusAfastamento::CONCLUIDO->value,
                StatusAfastamento::INTERROMPIDO->value,
            ])
            ->get()
            ->sum(function ($solicitacao): int {
                $dias = (int) ($solicitacao->dias_aprovados ?: $solicitacao->dias_solicitados);

                if ($solicitacao->status === StatusAfastamento::INTERROMPIDO) {
                    $dias -= (int) $solicitacao->interrupcoes
                        ->where('saldo_devolvido', true)
                        ->sum('dias_restantes');
                }

                return max(0, $dias);
            });

        $periodo->forceFill([
            'dias_usufruidos' => (int) $usados,
            'dias_disponiveis' => max(0, (int) $periodo->dias_direito - (int) $usados),
        ]);

        $periodo->status = $this->statusPeriodo($periodo);
        $periodo->save();

        return $periodo->refresh();
    }

    private function dataIngresso(Model $servidor): ?CarbonImmutable
    {
        $table = $servidor->getTable();

        foreach (self::CAMPOS_DATA_INGRESSO as $campo) {
            if (Schema::hasColumn($table, $campo) && filled($servidor->getAttribute($campo))) {
                return CarbonImmutable::parse($servidor->getAttribute($campo))->startOfDay();
            }
        }

        return null;
    }

    private function statusPeriodo(AfastamentoPeriodoAquisitivo $periodo): StatusPeriodoAquisitivo
    {
        $hoje = CarbonImmutable::now()->startOfDay();

        if ($periodo->data_aquisicao && $hoje->lt(CarbonImmutable::parse($periodo->data_aquisicao))) {
            return StatusPeriodoAquisitivo::EM_AQUISICAO;
        }

        if ((int) $periodo->dias_disponiveis <= 0) {
            return StatusPeriodoAquisitivo::USUFRUIDO;
        }

        if ((int) $periodo->dias_usufruidos > 0) {
            return StatusPeriodoAquisitivo::PARCIALMENTE_USUFRUIDO;
        }

        return StatusPeriodoAquisitivo::ADQUIRIDO;
    }

    private function tipos(TipoAfastamento|string|null $tipo): array
    {
        if ($tipo) {
            return [$tipo instanceof TipoAfastamento ? $tipo : TipoAfastamento::from($tipo)];
        }

        return TipoAfastamento::cases();
    }

    private function emptySummary(bool $dryRun): array
    {
        return [
            'criados' => 0,
            'atualizados' => 0,
            'ignorados' => 0,
            'erros' => 0,
            'avisos' => [],
            'dry_run' => $dryRun,
        ];
    }

    private function mergeSummary(array $base, array $partial): array
    {
        foreach (['criados', 'atualizados', 'ignorados', 'erros'] as $key) {
            $base[$key] += $partial[$key] ?? 0;
        }

        $base['avisos'] = array_values(array_merge($base['avisos'], $partial['avisos'] ?? []));

        return $base;
    }
}
