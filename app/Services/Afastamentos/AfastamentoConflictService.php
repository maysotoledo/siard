<?php

namespace App\Services\Afastamentos;

use App\Enums\FuncaoOperacional;
use App\Enums\NivelImpacto;
use App\Enums\StatusAfastamento;
use App\Models\AfastamentoPeriodoBloqueado;
use App\Models\AfastamentoRegraOperacional;
use App\Models\AfastamentoSolicitacao;
use App\Models\User;

class AfastamentoConflictService
{
    public function detectar(AfastamentoSolicitacao $solicitacao): array
    {
        $conflitos = [];

        $mesmoServidor = AfastamentoSolicitacao::query()
            ->where('user_id', $solicitacao->user_id)
            ->whereKeyNot($solicitacao->id ?? 0)
            ->whereDate('data_inicio', '<=', $solicitacao->data_fim)
            ->whereDate('data_fim', '>=', $solicitacao->data_inicio)
            ->whereIn('status', [
                StatusAfastamento::SOLICITADO->value,
                StatusAfastamento::EM_ANALISE->value,
                StatusAfastamento::APROVADO->value,
            ])
            ->exists();

        if ($mesmoServidor) {
            $conflitos[] = $this->conflito(NivelImpacto::CRITICO, 'Servidor já possui afastamento no período.', 'Escolha período sem sobreposição para o mesmo servidor.');
        }

        $bloqueado = AfastamentoPeriodoBloqueado::query()
            ->ativo()
            ->where(function ($query) use ($solicitacao): void {
                $query
                    ->whereNull('tipo_afastamento')
                    ->orWhere('tipo_afastamento', $solicitacao->tipo_afastamento->value);
            })
            ->whereDate('data_inicio', '<=', $solicitacao->data_fim)
            ->whereDate('data_fim', '>=', $solicitacao->data_inicio)
            ->first();

        if ($bloqueado) {
            $conflitos[] = $this->conflito(NivelImpacto::CRITICO, 'Período bloqueado: ' . $bloqueado->motivo, 'Ajuste a data para fora do período bloqueado.');
        }

        $conflitos = array_merge($conflitos, $this->conflitosOperacionais($solicitacao));

        return $conflitos;
    }

    public function possuiCritico(AfastamentoSolicitacao $solicitacao): bool
    {
        return collect($this->detectar($solicitacao))
            ->contains(fn (array $conflito): bool => ($conflito['nivel'] ?? null) === NivelImpacto::CRITICO->value);
    }

    private function conflitosOperacionais(AfastamentoSolicitacao $solicitacao): array
    {
        $user = $solicitacao->user ?: User::query()->find($solicitacao->user_id);
        $funcao = $user?->funcao_operacional;

        if (! $user || ! $funcao || ! $solicitacao->data_inicio || ! $solicitacao->data_fim) {
            return [];
        }

        $operacional = app(AfastamentoOperacionalService::class);
        $conflitos = [];
        $conflitoPrioridade = $this->conflitoPrioridade($solicitacao, $user, $funcao);
        if ($conflitoPrioridade) {
            $conflitos[] = $conflitoPrioridade;
        }

        if ($operacional->servidorEstaEmCobertura($user, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id)) {
            $conflitos[] = $this->conflito(NivelImpacto::CRITICO, 'Servidor está designado como cobertura de plantão no período.', 'Cancele ou substitua a cobertura antes de aprovar este afastamento.', 'operacional');
        }

        if ($funcao === FuncaoOperacional::IPC_EXPEDIENTE) {
            $disponiveisAposAfastamento = $operacional->disponiveisDaFuncao($funcao, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id) - 1;
            $minimo = $operacional->minimoDisponivel($funcao);

            if ($disponiveisAposAfastamento < $minimo) {
                $conflitos[] = $this->conflito(
                    NivelImpacto::CRITICO,
                    "Afastamento deixará IPC expediente com {$disponiveisAposAfastamento} disponível(is), abaixo do mínimo de {$minimo}.",
                    'Escolha outro período ou recomponha o expediente antes da aprovação.',
                    'operacional',
                );
            } elseif ($disponiveisAposAfastamento === $minimo) {
                $conflitos[] = $this->conflito(NivelImpacto::MODERADO, 'IPC expediente ficará no mínimo operacional.', 'Aprovar somente se não houver cobertura de plantão concorrente.', 'operacional');
            }
        }

        if ($funcao === FuncaoOperacional::IPC_PLANTAO) {
            if ($operacional->coberturaAprovada($solicitacao)) {
                if (! $operacional->expedienteFicaComMinimoAposCobertura($solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id)) {
                    $conflitos[] = $this->conflito(NivelImpacto::CRITICO, 'Cobertura de plantão deixará IPC expediente abaixo do mínimo.', 'Defina outra cobertura ou replaneje o período.', 'operacional');
                } else {
                    $conflitos[] = $this->conflito(NivelImpacto::MODERADO, 'Plantão coberto por IPC expediente.', 'Confirme a cobertura aprovada antes da decisão.', 'operacional');
                }
            } elseif ($operacional->servidoresDisponiveisParaCobertura($solicitacao) === []) {
                $conflitos[] = $this->conflito(NivelImpacto::CRITICO, 'IPC plantão sem cobertura disponível.', 'Indique cobertura válida de IPC expediente ou replaneje o afastamento.', 'operacional');
            } else {
                $conflitos[] = $this->conflito(NivelImpacto::ALTO, 'IPC plantão exige cobertura aprovada.', 'Defina e aprove uma cobertura de IPC expediente antes da aprovação.', 'operacional');
            }
        }

        $regraLegada = AfastamentoRegraOperacional::query()
            ->ativa()
            ->where(function ($query) use ($funcao): void {
                $query
                    ->where('funcao_operacional', $funcao->value)
                    ->orWhere('cargo', $funcao->role())
                    ->orWhere('funcao', $funcao->role());
            })
            ->orderByDesc('id')
            ->first();

        $simultaneos = $operacional->afastadosDaFuncao($funcao, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id);
        if ($regraLegada && ! $regraLegada->funcao_operacional && $simultaneos >= (int) $regraLegada->maximo_afastados_simultaneos) {
            $conflitos[] = $this->conflito(NivelImpacto::CRITICO, 'Excesso de afastados simultâneos para ' . $funcao->label() . '.', 'Escolha período com menor concentração ou ajuste a regra operacional com justificativa.', 'operacional');
        }

        return $conflitos;
    }

    private function conflitoPrioridade(AfastamentoSolicitacao $solicitacao, User $user, FuncaoOperacional $funcao): ?array
    {
        $concorrente = AfastamentoSolicitacao::query()
            ->with('user')
            ->whereKeyNot($solicitacao->id ?? 0)
            ->whereDate('data_inicio', '<=', $solicitacao->data_fim)
            ->whereDate('data_fim', '>=', $solicitacao->data_inicio)
            ->whereIn('status', [StatusAfastamento::SOLICITADO->value, StatusAfastamento::EM_ANALISE->value, StatusAfastamento::APROVADO->value])
            ->whereHas('user.roles', fn ($query) => $query->where('name', $funcao->role()))
            ->first();

        if (! $concorrente?->user) {
            return null;
        }

        $prioridade = app(AfastamentoPrioridadeService::class);
        $atual = $prioridade->calcularPrioridadeServidor($user, $solicitacao->tipo_afastamento);
        $outro = $prioridade->calcularPrioridadeServidor($concorrente->user, $solicitacao->tipo_afastamento);
        $preferido = $atual['score'] >= $outro['score'] ? $user->name : $concorrente->user->name;

        return $this->conflito(
            NivelImpacto::MODERADO,
            "Há solicitação conflitante com {$concorrente->user->name}. Prioridade recomendada: {$preferido}.",
            'A antiguidade é critério de desempate, mas a decisão continua subordinada ao interesse do serviço, efetivo mínimo e cobertura operacional.',
            'prioridade',
        );
    }

    private function conflito(NivelImpacto $nivel, string $mensagem, string $sugestao, string $origem = 'geral'): array
    {
        return [
            'nivel' => $nivel->value,
            'mensagem' => $mensagem,
            'sugestao' => $sugestao,
            'origem' => $origem,
        ];
    }
}
