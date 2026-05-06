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
use Illuminate\Support\Facades\Auth;

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
        return $this->filaCobertura($solicitacao)
            ->mapWithKeys(function (array $item): array {
                /** @var User $user */
                $user = $item['user'];
                $rotulo = $user->name;
                if ($user->funcao_operacional instanceof FuncaoOperacional) {
                    $rotulo .= ' ('.$user->funcao_operacional->label().')';
                }

                return [$user->id => $rotulo];
            })
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function servidoresDisponiveisParaCoberturaLista(AfastamentoSolicitacao $solicitacao): \Illuminate\Support\Collection
    {
        $funcaoAfastado = $solicitacao->user?->funcao_operacional;

        if (! $solicitacao->user || ! $funcaoAfastado instanceof FuncaoOperacional) {
            return collect();
        }

        $funcoesCandidatas = $funcaoAfastado->podeSerCobertaPor();

        if (empty($funcoesCandidatas)) {
            return collect();
        }

        // Para qualquer função candidata que não seja a própria do afastado, exigimos
        // que o mínimo operacional dela continue garantido após ceder 1 servidor.
        $funcoesValidas = collect($funcoesCandidatas)
            ->filter(fn (FuncaoOperacional $candidata): bool => $candidata === $funcaoAfastado
                ? true
                : $this->funcaoFicaComMinimoApos($candidata, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id))
            ->values();

        if ($funcoesValidas->isEmpty()) {
            return collect();
        }

        $roles = $funcoesValidas->map(fn (FuncaoOperacional $f): string => $f->role())->all();

        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', $roles))
            ->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', 'ipc_chefe'))
            ->whereKeyNot($solicitacao->user_id)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => ! $this->servidorEstaAfastado($user, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id)
                && ! $this->servidorEstaEmCobertura($user, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id))
            ->values();
    }

    public function sugerirServidorCobertura(AfastamentoSolicitacao $solicitacao): ?User
    {
        $primeiro = $this->filaCobertura($solicitacao)->first();

        return $primeiro['user'] ?? null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{user: User, coberturas: int, ultima_cobertura: ?string}>
     */
    public function filaCobertura(AfastamentoSolicitacao $solicitacao): \Illuminate\Support\Collection
    {
        return $this->servidoresDisponiveisParaCoberturaLista($solicitacao)
            ->map(fn (User $user): array => [
                'user' => $user,
                'coberturas' => $this->totalCoberturasDoServidor($user, $solicitacao->id),
                'ultima_cobertura' => $this->ultimaCoberturaDoServidor($user, $solicitacao->id),
            ])
            ->sort(fn (array $a, array $b): int => $a['coberturas'] <=> $b['coberturas']
                ?: ($a['ultima_cobertura'] ? strtotime($a['ultima_cobertura']) : 0) <=> ($b['ultima_cobertura'] ? strtotime($b['ultima_cobertura']) : 0)
                ?: ($a['user']->data_ingresso_unidade?->timestamp ?? PHP_INT_MAX) <=> ($b['user']->data_ingresso_unidade?->timestamp ?? PHP_INT_MAX)
                ?: ($a['user']->data_ingresso_carreira?->timestamp ?? PHP_INT_MAX) <=> ($b['user']->data_ingresso_carreira?->timestamp ?? PHP_INT_MAX)
                ?: $a['user']->name <=> $b['user']->name)
            ->values();
    }

    public function sugerirCobertura(AfastamentoSolicitacao $solicitacao): ?AfastamentoCoberturaPlantao
    {
        $existente = $solicitacao->coberturasPlantao()
            ->whereIn('status', ['sugerida', 'aprovada'])
            ->latest()
            ->first();

        if ($existente instanceof AfastamentoCoberturaPlantao) {
            return $existente;
        }

        $servidor = $this->sugerirServidorCobertura($solicitacao);

        if (! $servidor instanceof User) {
            return null;
        }

        return $this->definirCobertura($solicitacao, $servidor, 'sugerida', 'Sugerido automaticamente pela análise operacional.');
    }

    public function aprovarCoberturaSugerida(AfastamentoSolicitacao $solicitacao): ?AfastamentoCoberturaPlantao
    {
        $aprovada = $this->coberturaAprovada($solicitacao);

        if ($aprovada instanceof AfastamentoCoberturaPlantao) {
            return $aprovada;
        }

        $sugerida = $solicitacao->coberturasPlantao()
            ->where('status', 'sugerida')
            ->latest()
            ->first();

        if (! $sugerida instanceof AfastamentoCoberturaPlantao) {
            $sugerida = $this->sugerirCobertura($solicitacao);
        }

        if (! $sugerida instanceof AfastamentoCoberturaPlantao) {
            return null;
        }

        return $this->definirCobertura(
            $solicitacao,
            (int) $sugerida->servidor_cobertura_id,
            'aprovada',
            $sugerida->observacao ?: 'Cobertura sugerida automaticamente e aprovada junto ao afastamento.',
        );
    }

    public function definirCobertura(AfastamentoSolicitacao $solicitacao, User|int $servidor, string $status = 'sugerida', ?string $observacao = null): AfastamentoCoberturaPlantao
    {
        $solicitacao->loadMissing('user');
        $coberturaUser = $servidor instanceof User ? $servidor : User::query()->findOrFail($servidor);
        $funcaoOrigem = $coberturaUser->funcao_operacional;
        $funcaoDestino = $solicitacao->user?->funcao_operacional;

        if (! $funcaoOrigem instanceof FuncaoOperacional || ! $funcaoDestino instanceof FuncaoOperacional) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'servidor_cobertura_id' => 'Não foi possível determinar a função operacional dos servidores envolvidos.',
            ]);
        }

        if (! in_array($funcaoOrigem, $funcaoDestino->podeSerCobertaPor(), true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'servidor_cobertura_id' => "A função {$funcaoOrigem->label()} não pode cobrir {$funcaoDestino->label()}.",
            ]);
        }

        if ($this->servidorEstaAfastado($coberturaUser, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id)
            || $this->servidorEstaEmCobertura($coberturaUser, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'servidor_cobertura_id' => 'O servidor selecionado não está disponível para cobertura neste período.',
            ]);
        }

        if ($funcaoOrigem !== $funcaoDestino && ! $this->funcaoFicaComMinimoApos($funcaoOrigem, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'servidor_cobertura_id' => "A cobertura deixaria {$funcaoOrigem->label()} abaixo do mínimo operacional.",
            ]);
        }

        $solicitacao->coberturasPlantao()
            ->whereIn('status', ['sugerida', 'aprovada'])
            ->where('servidor_cobertura_id', '!=', $coberturaUser->id)
            ->update(['status' => 'cancelada']);

        return AfastamentoCoberturaPlantao::query()->updateOrCreate(
            [
                'afastamento_solicitacao_id' => $solicitacao->id,
                'servidor_cobertura_id' => $coberturaUser->id,
            ],
            [
                'servidor_plantao_afastado_id' => $solicitacao->user_id,
                'funcao_origem' => $funcaoOrigem,
                'funcao_destino' => $funcaoDestino,
                'data_inicio' => $solicitacao->data_inicio,
                'data_fim' => $solicitacao->data_fim,
                'status' => $status,
                'aprovado_por' => $status === 'aprovada' ? Auth::id() : null,
                'aprovado_em' => $status === 'aprovada' ? now() : null,
                'observacao' => $observacao,
            ],
        );
    }

    private function totalCoberturasDoServidor(User $user, ?int $ignorarSolicitacaoId = null): int
    {
        return AfastamentoCoberturaPlantao::query()
            ->when($ignorarSolicitacaoId, fn (Builder $query) => $query->where('afastamento_solicitacao_id', '!=', $ignorarSolicitacaoId))
            ->where('servidor_cobertura_id', $user->id)
            ->whereIn('status', ['sugerida', 'aprovada'])
            ->count();
    }

    private function ultimaCoberturaDoServidor(User $user, ?int $ignorarSolicitacaoId = null): ?string
    {
        return AfastamentoCoberturaPlantao::query()
            ->when($ignorarSolicitacaoId, fn (Builder $query) => $query->where('afastamento_solicitacao_id', '!=', $ignorarSolicitacaoId))
            ->where('servidor_cobertura_id', $user->id)
            ->whereIn('status', ['sugerida', 'aprovada'])
            ->max('data_fim');
    }
}
