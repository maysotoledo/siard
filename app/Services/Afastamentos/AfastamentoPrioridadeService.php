<?php

namespace App\Services\Afastamentos;

use App\Enums\NivelImpacto;
use App\Enums\NivelPrioridadeAfastamento;
use App\Enums\TipoAfastamento;
use App\Models\AfastamentoPeriodoAquisitivo;
use App\Models\AfastamentoPrioridadeRegra;
use App\Models\AfastamentoSolicitacao;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class AfastamentoPrioridadeService
{
    public function calcularPrioridadeServidor(User|int $servidor, TipoAfastamento|string $tipoAfastamento): array
    {
        $user = $servidor instanceof User ? $servidor : User::query()->findOrFail($servidor);
        $tipo = $tipoAfastamento instanceof TipoAfastamento ? $tipoAfastamento : TipoAfastamento::from($tipoAfastamento);
        $regra = $this->regra($tipo, $user);
        $motivos = [];
        $score = 0;

        if ($regra->usar_antiguidade_servico_publico) {
            $pontos = $this->anosDesde($this->dataServicoPublico($user)) * (int) $regra->peso_antiguidade_servico_publico;
            $score += $pontos;
            $motivos[] = "Antiguidade no serviço público: {$pontos} ponto(s).";
        }

        if ($regra->usar_antiguidade_carreira) {
            $pontos = $this->anosDesde($user->data_ingresso_carreira ?: $user->data_ingresso) * (int) $regra->peso_antiguidade_carreira;
            $score += $pontos;
            $motivos[] = "Antiguidade na carreira: {$pontos} ponto(s).";
        }

        if ($regra->usar_antiguidade_unidade) {
            $pontos = $this->anosDesde($user->data_ingresso_unidade ?: $user->data_ingresso) * (int) $regra->peso_antiguidade_unidade;
            $score += $pontos;
            $motivos[] = "Antiguidade na unidade: {$pontos} ponto(s).";
        }

        $periodoMaisAntigo = $this->periodoMaisAntigo($user, $tipo);
        if ($periodoMaisAntigo) {
            $anos = max(1, $this->anosDesde($periodoMaisAntigo->data_aquisicao));
            $pontos = $anos * (int) $regra->peso_periodo_aquisitivo_mais_antigo;
            $score += $pontos;
            $motivos[] = "Período aquisitivo mais antigo: {$pontos} ponto(s).";

            if ($anos >= 2) {
                $score += (int) $regra->peso_saldo_vencido_ou_antigo;
                $motivos[] = 'Saldo antigo/vencido: '.$regra->peso_saldo_vencido_ou_antigo.' ponto(s).';
            }
        }

        $ultimoGozo = $this->ultimoGozo($user, $tipo);
        $anosSemGozo = $ultimoGozo ? $this->anosDesde($ultimoGozo) : $this->anosDesde($this->dataServicoPublico($user));
        if ($anosSemGozo > 0) {
            $pontos = $anosSemGozo * (int) $regra->peso_tempo_sem_gozo;
            $score += $pontos;
            $motivos[] = "Tempo sem gozo: {$pontos} ponto(s).";
        }

        return [
            'score' => max(0, (int) $score),
            'nivel' => NivelPrioridadeAfastamento::fromScore((int) $score),
            'motivo' => implode(' ', $motivos),
        ];
    }

    public function calcularRanking(TipoAfastamento|string $tipoAfastamento, ?int $unidadeId = null, mixed $funcaoOperacional = null): array
    {
        $tipo = $tipoAfastamento instanceof TipoAfastamento ? $tipoAfastamento : TipoAfastamento::from($tipoAfastamento);

        return User::query()
            ->when($unidadeId && Schema::hasColumn('users', 'unidade_id'), fn (Builder $query) => $query->where('unidade_id', $unidadeId))
            ->when($funcaoOperacional, fn (Builder $query) => $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', $funcaoOperacional->role())))
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($tipo): array {
                $prioridade = $this->calcularPrioridadeServidor($user, $tipo);

                return [
                    'user' => $user,
                    'score' => $prioridade['score'],
                    'nivel' => $prioridade['nivel'],
                    'motivo' => $prioridade['motivo'],
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->map(function (array $item, int $index): array {
                $item['posicao'] = $index + 1;

                return $item;
            })
            ->all();
    }

    public function compararSolicitacoes(AfastamentoSolicitacao $solicitacaoA, AfastamentoSolicitacao $solicitacaoB): int
    {
        $a = $solicitacaoA->prioridade_score ?? $this->calcularPrioridadeServidor($solicitacaoA->user, $solicitacaoA->tipo_afastamento)['score'];
        $b = $solicitacaoB->prioridade_score ?? $this->calcularPrioridadeServidor($solicitacaoB->user, $solicitacaoB->tipo_afastamento)['score'];

        return $b <=> $a;
    }

    public function explicarPrioridade(User|int $servidor, TipoAfastamento|string $tipoAfastamento): string
    {
        return $this->calcularPrioridadeServidor($servidor, $tipoAfastamento)['motivo'];
    }

    public function recalcularPrioridadesPendentes(): int
    {
        $total = 0;
        AfastamentoSolicitacao::query()
            ->with('user')
            ->pendentes()
            ->chunkById(50, function ($solicitacoes) use (&$total): void {
                foreach ($solicitacoes as $solicitacao) {
                    $this->atualizarSolicitacao($solicitacao);
                    $total++;
                }
            });

        return $total;
    }

    public function atualizarSolicitacao(AfastamentoSolicitacao $solicitacao): AfastamentoSolicitacao
    {
        $solicitacao->loadMissing('user');
        $prioridade = $this->calcularPrioridadeServidor($solicitacao->user, $solicitacao->tipo_afastamento);
        $ranking = collect($this->calcularRanking($solicitacao->tipo_afastamento));
        $posicao = $ranking->firstWhere('user.id', $solicitacao->user_id)['posicao'] ?? null;

        $solicitacao->forceFill([
            'prioridade_score' => $prioridade['score'],
            'prioridade_nivel' => $prioridade['nivel']->value,
            'prioridade_posicao' => $posicao,
            'prioridade_motivo' => $prioridade['motivo'],
            'prioridade_calculada_em' => now(),
        ])->save();

        return $solicitacao->refresh();
    }

    public function regra(TipoAfastamento $tipo, User $user): AfastamentoPrioridadeRegra
    {
        return AfastamentoPrioridadeRegra::query()
            ->ativa()
            ->where(function (Builder $query) use ($tipo): void {
                $query->whereNull('tipo_afastamento')->orWhere('tipo_afastamento', $tipo->value);
            })
            ->where(function (Builder $query) use ($user): void {
                $query->whereNull('funcao_operacional')->orWhere('funcao_operacional', $user->funcao_operacional?->value);
            })
            ->orderByRaw('tipo_afastamento is not null desc')
            ->orderByRaw('funcao_operacional is not null desc')
            ->orderByDesc('id')
            ->first()
            ?? AfastamentoPrioridadeRegra::query()->create(['nome' => 'Regra Geral de Prioridade']);
    }

    private function periodoMaisAntigo(User $user, TipoAfastamento $tipo): ?AfastamentoPeriodoAquisitivo
    {
        return AfastamentoPeriodoAquisitivo::query()
            ->where('user_id', $user->id)
            ->where('tipo_afastamento', $tipo->value)
            ->where('dias_disponiveis', '>', 0)
            ->whereDate('data_aquisicao', '<=', now()->toDateString())
            ->orderBy('data_aquisicao')
            ->first();
    }

    private function ultimoGozo(User $user, TipoAfastamento $tipo): ?CarbonInterface
    {
        return AfastamentoSolicitacao::query()
            ->where('user_id', $user->id)
            ->where('tipo_afastamento', $tipo->value)
            ->whereIn('status', ['aprovado', 'concluido'])
            ->orderByDesc('data_fim')
            ->value('data_fim');
    }

    private function dataServicoPublico(User $user): ?CarbonInterface
    {
        return $user->data_ingresso_servico_publico
            ?: $user->data_ingresso
            ?: $user->data_posse
            ?: $user->data_exercicio
            ?: $user->admitted_at;
    }

    private function anosDesde(mixed $data): int
    {
        return $data ? max(0, (int) Carbon::parse($data)->diffInYears(now())) : 0;
    }
}
