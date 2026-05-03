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

    public function expedienteFicaComMinimoAposCobertura(CarbonInterface|string $inicio, CarbonInterface|string $fim, ?int $ignorarSolicitacaoId = null): bool
    {
        $disponiveis = $this->disponiveisDaFuncao(FuncaoOperacional::IPC_EXPEDIENTE, $inicio, $fim, $ignorarSolicitacaoId);

        return ($disponiveis - 1) >= $this->minimoDisponivel(FuncaoOperacional::IPC_EXPEDIENTE);
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
        if (! $solicitacao->user || $solicitacao->user->funcao_operacional !== FuncaoOperacional::IPC_PLANTAO) {
            return [];
        }

        if (! $this->expedienteFicaComMinimoAposCobertura($solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id)) {
            return [];
        }

        return $this->usuariosDaFuncao(FuncaoOperacional::IPC_EXPEDIENTE)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => ! $this->servidorEstaAfastado($user, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id)
                && ! $this->servidorEstaEmCobertura($user, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id))
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name])
            ->all();
    }
}
