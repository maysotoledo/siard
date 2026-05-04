<?php

namespace App\Services\Afastamentos;

use App\Enums\NivelImpacto;
use App\Enums\NivelPrioridadeAfastamento;
use App\Enums\StatusAfastamento;
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

    public function analisarConflitosPorPrioridade(AfastamentoSolicitacao $solicitacao): array
    {
        $solicitacao->loadMissing('user');
        $user = $solicitacao->user;
        $funcao = $user?->funcao_operacional;

        if (! $user || ! $funcao || ! $solicitacao->data_inicio || ! $solicitacao->data_fim) {
            return [
                'criterios' => $this->criteriosDesempate(),
                'ranking' => [],
                'preferido' => null,
                'mensagem' => 'Não foi possível montar ranking: servidor, função ou período não informado.',
            ];
        }

        $solicitacoes = AfastamentoSolicitacao::query()
            ->with(['user.roles'])
            ->whereDate('data_inicio', '<=', $solicitacao->data_fim)
            ->whereDate('data_fim', '>=', $solicitacao->data_inicio)
            ->whereIn('status', [
                StatusAfastamento::SOLICITADO->value,
                StatusAfastamento::EM_ANALISE->value,
                StatusAfastamento::APROVADO->value,
            ])
            ->whereHas('user.roles', fn (Builder $query) => $query->where('name', $funcao->role()))
            ->get();

        if (! $solicitacoes->contains('id', $solicitacao->id)) {
            $solicitacoes->push($solicitacao);
        }

        $ranking = $solicitacoes
            ->unique('id')
            ->map(fn (AfastamentoSolicitacao $item): array => $this->linhaRankingConflito($item, $solicitacao))
            ->sort(function (array $a, array $b): int {
                return $a['data_carreira_sort'] <=> $b['data_carreira_sort']
                    ?: $a['data_unidade_sort'] <=> $b['data_unidade_sort']
                    ?: $a['solicitado_em_sort'] <=> $b['solicitado_em_sort']
                    ?: $a['servidor'] <=> $b['servidor'];
            })
            ->values()
            ->map(function (array $item, int $index): array {
                $item['posicao'] = $index + 1;
                unset($item['data_carreira_sort'], $item['data_unidade_sort'], $item['solicitado_em_sort']);

                return $item;
            })
            ->all();

        $preferido = $ranking[0] ?? null;

        return [
            'criterios' => $this->criteriosDesempate(),
            'ranking' => $ranking,
            'preferido' => $preferido,
            'mensagem' => $preferido
                ? "Fila de prioridade recomendada: {$preferido['servidor']} em 1º lugar. A chefia deve confirmar se o interesse do serviço, efetivo mínimo e cobertura operacional permitem a decisão."
                : 'Nenhum servidor conflitante encontrado para montar fila de prioridade.',
        ];
    }

    public function explicarPrioridade(User|int $servidor, TipoAfastamento|string $tipoAfastamento): string
    {
        return $this->calcularPrioridadeServidor($servidor, $tipoAfastamento)['motivo'];
    }

    public function criteriosDesempate(): array
    {
        return [
            '1. Data de ingresso na carreira mais antiga.',
            '2. Data de ingresso na unidade mais antiga.',
            '3. Solicitação cadastrada primeiro.',
        ];
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

    private function linhaRankingConflito(AfastamentoSolicitacao $solicitacao, AfastamentoSolicitacao $referencia): array
    {
        $user = $solicitacao->user;
        $dataCarreira = $this->dataCarreira($user);
        $dataUnidade = $this->dataUnidade($user);
        $solicitadoEm = $solicitacao->created_at ?: now();

        return [
            'solicitacao_id' => $solicitacao->id,
            'servidor' => $user?->name ?? 'Servidor não identificado',
            'status' => $solicitacao->status?->label() ?? (string) $solicitacao->status?->value,
            'periodo' => ($solicitacao->data_inicio?->format('d/m/Y') ?? '-') . ' a ' . ($solicitacao->data_fim?->format('d/m/Y') ?? '-'),
            'data_carreira' => $dataCarreira ? Carbon::parse($dataCarreira)->format('d/m/Y') : '-',
            'data_unidade' => $dataUnidade ? Carbon::parse($dataUnidade)->format('d/m/Y') : '-',
            'solicitado_em' => Carbon::parse($solicitadoEm)->format('d/m/Y H:i'),
            'eh_solicitacao_atual' => (int) $solicitacao->id === (int) $referencia->id,
            'motivo' => $this->explicarLinhaRanking($user, $dataCarreira, $dataUnidade, $solicitadoEm),
            'data_carreira_sort' => $this->sortDate($dataCarreira),
            'data_unidade_sort' => $this->sortDate($dataUnidade),
            'solicitado_em_sort' => Carbon::parse($solicitadoEm)->timestamp,
        ];
    }

    private function explicarLinhaRanking(?User $user, mixed $dataCarreira, mixed $dataUnidade, mixed $solicitadoEm): string
    {
        return 'Carreira: ' . ($dataCarreira ? Carbon::parse($dataCarreira)->format('d/m/Y') : 'sem data') .
            '; Unidade: ' . ($dataUnidade ? Carbon::parse($dataUnidade)->format('d/m/Y') : 'sem data') .
            '; Solicitação: ' . Carbon::parse($solicitadoEm)->format('d/m/Y H:i') . '.';
    }

    private function dataCarreira(?User $user): mixed
    {
        return $user?->data_ingresso_carreira ?: $user?->data_ingresso;
    }

    private function dataUnidade(?User $user): mixed
    {
        return $user?->data_ingresso_unidade ?: $user?->data_ingresso;
    }

    private function sortDate(mixed $data): int
    {
        return $data ? Carbon::parse($data)->timestamp : PHP_INT_MAX;
    }
}
