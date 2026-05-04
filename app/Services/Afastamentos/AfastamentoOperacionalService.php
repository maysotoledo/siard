<?php

namespace App\Services\Afastamentos;

use App\Enums\FuncaoOperacional;
use App\Enums\StatusAfastamento;
use App\Models\AfastamentoCoberturaPlantao;
use App\Models\AfastamentoRegraOperacional;
use App\Models\AfastamentoSolicitacao;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class AfastamentoOperacionalService
{
    public function funcao(User $user): ?FuncaoOperacional
    {
        return $user->funcao_operacional;
    }

    public function regra(FuncaoOperacional $funcao): ?AfastamentoRegraOperacional
    {
        return AfastamentoRegraOperacional::query()
            ->ativa()
            ->where('funcao_operacional', $funcao->value)
            ->orderByDesc('id')
            ->first();
    }

    public function minimoDisponivel(FuncaoOperacional $funcao): int
    {
        $regra = $this->regra($funcao);

        return (int) ($regra?->minimo_disponivel ?? match ($funcao) {
            FuncaoOperacional::IPC_EXPEDIENTE => 2,
            default => 1,
        });
    }

    public function usuariosDaFuncao(FuncaoOperacional $funcao): Builder
    {
        return User::query()->whereHas('roles', fn (Builder $query) => $query->where('name', $funcao->role()));
    }

    public function totalDaFuncao(FuncaoOperacional $funcao): int
    {
        return $this->usuariosDaFuncao($funcao)->count();
    }

    public function afastadosDaFuncao(FuncaoOperacional $funcao, CarbonInterface|string $inicio, CarbonInterface|string $fim, ?int $ignorarSolicitacaoId = null): int
    {
        return AfastamentoSolicitacao::query()
            ->when($ignorarSolicitacaoId, fn (Builder $query) => $query->whereKeyNot($ignorarSolicitacaoId))
            ->whereDate('data_inicio', '<=', $fim)
            ->whereDate('data_fim', '>=', $inicio)
            ->whereIn('status', [
                StatusAfastamento::SOLICITADO->value,
                StatusAfastamento::EM_ANALISE->value,
                StatusAfastamento::APROVADO->value,
            ])
            ->whereHas('user.roles', fn (Builder $query) => $query->where('name', $funcao->role()))
            ->count();
    }

    public function coberturasAprovadasDaFuncao(FuncaoOperacional $funcao, CarbonInterface|string $inicio, CarbonInterface|string $fim, ?int $ignorarSolicitacaoId = null): int
    {
        return AfastamentoCoberturaPlantao::query()
            ->when($ignorarSolicitacaoId, fn (Builder $query) => $query->where('afastamento_solicitacao_id', '!=', $ignorarSolicitacaoId))
            ->where('funcao_origem', $funcao->value)
            ->where('status', 'aprovada')
            ->whereDate('data_inicio', '<=', $fim)
            ->whereDate('data_fim', '>=', $inicio)
            ->count();
    }

    public function disponiveisDaFuncao(FuncaoOperacional $funcao, CarbonInterface|string $inicio, CarbonInterface|string $fim, ?int $ignorarSolicitacaoId = null): int
    {
        return max(0,
            $this->totalDaFuncao($funcao)
            - $this->afastadosDaFuncao($funcao, $inicio, $fim, $ignorarSolicitacaoId)
            - $this->coberturasAprovadasDaFuncao($funcao, $inicio, $fim, $ignorarSolicitacaoId)
        );
    }

    /**
     * Lista os servidores efetivamente disponíveis da função no período (não afastados e não em cobertura).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function disponiveisDaFuncaoLista(FuncaoOperacional $funcao, CarbonInterface|string $inicio, CarbonInterface|string $fim, ?int $ignorarSolicitacaoId = null): \Illuminate\Support\Collection
    {
        return $this->usuariosDaFuncao($funcao)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => ! $this->servidorEstaAfastado($user, $inicio, $fim, $ignorarSolicitacaoId)
                && ! $this->servidorEstaEmCobertura($user, $inicio, $fim, $ignorarSolicitacaoId))
            ->values();
    }

    public function expedienteFicaComMinimoAposCobertura(CarbonInterface|string $inicio, CarbonInterface|string $fim, ?int $ignorarSolicitacaoId = null): bool
    {
        return $this->funcaoFicaComMinimoApos(FuncaoOperacional::IPC_EXPEDIENTE, $inicio, $fim, $ignorarSolicitacaoId);
    }

    /**
     * Verifica se a função fica com o mínimo operacional após ceder 1 servidor para cobertura.
     */
    public function funcaoFicaComMinimoApos(FuncaoOperacional $funcao, CarbonInterface|string $inicio, CarbonInterface|string $fim, ?int $ignorarSolicitacaoId = null): bool
    {
        $disponiveis = $this->disponiveisDaFuncao($funcao, $inicio, $fim, $ignorarSolicitacaoId);

        return ($disponiveis - 1) >= $this->minimoDisponivel($funcao);
    }

    public function servidorEstaAfastado(User|int $user, CarbonInterface|string $inicio, CarbonInterface|string $fim, ?int $ignorarSolicitacaoId = null): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return AfastamentoSolicitacao::query()
            ->when($ignorarSolicitacaoId, fn (Builder $query) => $query->whereKeyNot($ignorarSolicitacaoId))
            ->where('user_id', $userId)
            ->whereDate('data_inicio', '<=', $fim)
            ->whereDate('data_fim', '>=', $inicio)
            ->whereIn('status', [
                StatusAfastamento::SOLICITADO->value,
                StatusAfastamento::EM_ANALISE->value,
                StatusAfastamento::APROVADO->value,
            ])
            ->exists();
    }

    public function servidorEstaEmCobertura(User|int $user, CarbonInterface|string $inicio, CarbonInterface|string $fim, ?int $ignorarSolicitacaoId = null): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return AfastamentoCoberturaPlantao::query()
            ->when($ignorarSolicitacaoId, fn (Builder $query) => $query->where('afastamento_solicitacao_id', '!=', $ignorarSolicitacaoId))
            ->where('servidor_cobertura_id', $userId)
            ->where('status', 'aprovada')
            ->whereDate('data_inicio', '<=', $fim)
            ->whereDate('data_fim', '>=', $inicio)
            ->exists();
    }

    public function coberturaAprovada(AfastamentoSolicitacao $solicitacao): ?AfastamentoCoberturaPlantao
    {
        return $solicitacao->coberturasPlantao()
            ->where('status', 'aprovada')
            ->latest()
            ->first();
    }

    public function servidoresDisponiveisParaCobertura(AfastamentoSolicitacao $solicitacao): array
    {
        $funcaoAfastado = $solicitacao->user?->funcao_operacional;

        if (! $solicitacao->user || ! $funcaoAfastado instanceof FuncaoOperacional) {
            return [];
        }

        $funcoesCandidatas = $funcaoAfastado->podeSerCobertaPor();

        if (empty($funcoesCandidatas)) {
            return [];
        }

        // Para qualquer função candidata que não seja a própria do afastado, exigimos
        // que o mínimo operacional dela continue garantido após ceder 1 servidor.
        $funcoesValidas = collect($funcoesCandidatas)
            ->filter(fn (FuncaoOperacional $candidata): bool => $candidata === $funcaoAfastado
                ? true
                : $this->funcaoFicaComMinimoApos($candidata, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id))
            ->values();

        if ($funcoesValidas->isEmpty()) {
            return [];
        }

        $roles = $funcoesValidas->map(fn (FuncaoOperacional $f): string => $f->role())->all();

        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', $roles))
            ->whereKeyNot($solicitacao->user_id)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => ! $this->servidorEstaAfastado($user, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id)
                && ! $this->servidorEstaEmCobertura($user, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id))
            ->mapWithKeys(function (User $user): array {
                $rotulo = $user->name;
                if ($user->funcao_operacional instanceof FuncaoOperacional) {
                    $rotulo .= ' ('.$user->funcao_operacional->label().')';
                }

                return [$user->id => $rotulo];
            })
            ->all();
    }
}
