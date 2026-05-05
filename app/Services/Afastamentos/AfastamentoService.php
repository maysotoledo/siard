<?php

namespace App\Services\Afastamentos;

use App\Enums\FuncaoOperacional;
use App\Enums\NivelImpacto;
use App\Enums\StatusAfastamento;
use App\Enums\TipoAfastamento;
use App\Models\AfastamentoHistorico;
use App\Models\AfastamentoInterrupcao;
use App\Models\AfastamentoSolicitacao;
use App\Services\Plantao\PlantaoSubstituicaoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AfastamentoService
{
    public function salvar(array $data, ?AfastamentoSolicitacao $solicitacao = null): AfastamentoSolicitacao
    {
        return DB::transaction(function () use ($data, $solicitacao): AfastamentoSolicitacao {
            $inicio = Carbon::parse($data['data_inicio'])->startOfDay();
            $fim = Carbon::parse($data['data_fim'])->startOfDay();
            $tipoEnum = TipoAfastamento::tryFrom((string) ($data['tipo_afastamento'] ?? ''));

            if ($fim->lt($inicio)) {
                throw ValidationException::withMessages(['data_fim' => 'A data final deve ser igual ou posterior à inicial.']);
            }

            // Atestado é retroativo por natureza — qualquer usuário pode registrar datas passadas.
            $isAtestado = $tipoEnum === TipoAfastamento::ATESTADO;

            if (! $isAtestado && ! $this->isAdmin() && $inicio->isPast()) {
                throw ValidationException::withMessages(['data_inicio' => 'Solicitação retroativa exige perfil administrativo.']);
            }

            $data['dias_solicitados'] = $inicio->diffInDays($fim) + 1;

            // Atestado é aprovado diretamente — sem fila de rascunho/análise.
            if ($isAtestado) {
                $data['status'] = $data['status'] ?? StatusAfastamento::APROVADO->value;
                $data['periodo_aquisitivo_id'] = null;
            } else {
                $data['status'] = $data['status'] ?? StatusAfastamento::RASCUNHO->value;
                $this->validarSaldoParaDados($data, $solicitacao);
            }

            $solicitacao = $solicitacao
                ? tap($solicitacao)->update($data)
                : AfastamentoSolicitacao::query()->create($data);

            app(AfastamentoOperacionalService::class)->sugerirCobertura($solicitacao->refresh()->loadMissing('user'));
            $this->recalcularImpacto($solicitacao);
            $this->historico($solicitacao, $solicitacao->wasRecentlyCreated ? 'criacao' : 'edicao', null, $solicitacao->status, 'Solicitação registrada/atualizada.');

            return $solicitacao->refresh();
        });
    }

    public function enviarParaAnalise(AfastamentoSolicitacao $solicitacao): AfastamentoSolicitacao
    {
        return $this->mudarStatus($solicitacao, StatusAfastamento::EM_ANALISE, 'envio_analise', 'Solicitação enviada para análise.');
    }

    public function aprovar(AfastamentoSolicitacao $solicitacao, string $justificativa, bool $forcar = false): AfastamentoSolicitacao
    {
        return DB::transaction(function () use ($solicitacao, $justificativa, $forcar): AfastamentoSolicitacao {
            if (trim($justificativa) === '') {
                throw ValidationException::withMessages(['justificativa_chefia' => 'A aprovação exige justificativa da chefia.']);
            }

            $this->recalcularImpacto($solicitacao);

            $solicitacao = $solicitacao->refresh()->loadMissing('user', 'coberturasPlantao');
            app(AfastamentoOperacionalService::class)->aprovarCoberturaSugerida($solicitacao);
            $solicitacao = $solicitacao->refresh()->loadMissing('user', 'coberturasPlantao');
            $this->recalcularImpacto($solicitacao);
            $solicitacao = $solicitacao->refresh()->loadMissing('user', 'coberturasPlantao');

            $this->validarOperacionalAntesDaAprovacao($solicitacao, $forcar);

            if (! $forcar && $solicitacao->nivel_impacto === NivelImpacto::CRITICO) {
                throw ValidationException::withMessages(['data_inicio' => 'Afastamento com impacto crítico exige super_admin e justificativa.']);
            }

            app(AfastamentoSaldoService::class)->validarSaldo($solicitacao);
            app(AfastamentoSaldoService::class)->abater($solicitacao);

            $old = $solicitacao->status;
            $solicitacao->forceFill([
                'status' => StatusAfastamento::APROVADO,
                'dias_aprovados' => $solicitacao->dias_aprovados ?: $solicitacao->dias_solicitados,
                'justificativa_chefia' => $justificativa,
                'aprovado_por' => Auth::id(),
                'aprovado_em' => now(),
            ])->save();

            if ($solicitacao->periodoAquisitivo) {
                app(AfastamentoPeriodoAquisitivoService::class)->recalcular($solicitacao->periodoAquisitivo);
            }

            $this->historico($solicitacao, 'aprovacao', $old, StatusAfastamento::APROVADO, 'Afastamento aprovado.');
            app(AfastamentoNotificationService::class)->notificarDecisao($solicitacao, 'Afastamento aprovado');

            // Propaga substituição automática nas escalas de plantão (se houver cobertura aprovada).
            app(PlantaoSubstituicaoService::class)->aplicar($solicitacao->refresh());

            return $solicitacao->refresh();
        });
    }

    public function indeferir(AfastamentoSolicitacao $solicitacao, string $justificativa): AfastamentoSolicitacao
    {
        if (trim($justificativa) === '') {
            throw ValidationException::withMessages(['justificativa_chefia' => 'O indeferimento exige justificativa.']);
        }

        $old = $solicitacao->status;
        $solicitacao->forceFill([
            'status' => StatusAfastamento::INDEFERIDO,
            'justificativa_chefia' => $justificativa,
            'indeferido_por' => Auth::id(),
            'indeferido_em' => now(),
        ])->save();

        $this->historico($solicitacao, 'indeferimento', $old, StatusAfastamento::INDEFERIDO, 'Afastamento indeferido.');
        app(AfastamentoNotificationService::class)->notificarDecisao($solicitacao, 'Afastamento indeferido');

        return $solicitacao->refresh();
    }

    public function cancelar(AfastamentoSolicitacao $solicitacao, string $justificativa): AfastamentoSolicitacao
    {
        if (trim($justificativa) === '') {
            throw ValidationException::withMessages(['justificativa_chefia' => 'O cancelamento exige justificativa.']);
        }

        return DB::transaction(function () use ($solicitacao, $justificativa): AfastamentoSolicitacao {
            if ($solicitacao->status === StatusAfastamento::APROVADO) {
                app(AfastamentoSaldoService::class)->devolver($solicitacao);
                // Remove permutas automáticas de plantão antes de cancelar.
                app(PlantaoSubstituicaoService::class)->reverter($solicitacao);
            }

            $old = $solicitacao->status;
            $solicitacao->forceFill([
                'status' => StatusAfastamento::CANCELADO,
                'justificativa_chefia' => $justificativa,
                'cancelado_por' => Auth::id(),
                'cancelado_em' => now(),
            ])->save();

            if ($solicitacao->periodoAquisitivo) {
                app(AfastamentoPeriodoAquisitivoService::class)->recalcular($solicitacao->periodoAquisitivo);
            }

            $this->historico($solicitacao, 'cancelamento', $old, StatusAfastamento::CANCELADO, 'Afastamento cancelado.');

            return $solicitacao->refresh();
        });
    }

    public function interromper(AfastamentoSolicitacao $solicitacao, Carbon|string $data, string $motivo): AfastamentoSolicitacao
    {
        $regra = app(AfastamentoPeriodoAquisitivoService::class)->regra($solicitacao->tipo_afastamento);
        if (! $regra->permite_interrupcao) {
            throw ValidationException::withMessages(['motivo' => 'A regra deste tipo de afastamento não permite interrupção automática.']);
        }

        return DB::transaction(function () use ($solicitacao, $data, $motivo, $regra): AfastamentoSolicitacao {
            $data = Carbon::parse($data)->startOfDay();
            $diasRestantes = max(0, $data->diffInDays($solicitacao->data_fim) + 1);

            if ($regra->devolve_saldo_ao_interromper) {
                app(AfastamentoSaldoService::class)->devolver($solicitacao, $diasRestantes);
            }

            AfastamentoInterrupcao::query()->create([
                'afastamento_solicitacao_id' => $solicitacao->id,
                'interrompido_por' => Auth::id(),
                'data_interrupcao' => $data->toDateString(),
                'motivo' => $motivo,
                'dias_restantes' => $diasRestantes,
                'saldo_devolvido' => (bool) $regra->devolve_saldo_ao_interromper,
            ]);

            $old = $solicitacao->status;
            $solicitacao->forceFill(['status' => StatusAfastamento::INTERROMPIDO])->save();
            if ($solicitacao->periodoAquisitivo) {
                app(AfastamentoPeriodoAquisitivoService::class)->recalcular($solicitacao->periodoAquisitivo);
            }
            $this->historico($solicitacao, 'interrupcao', $old, StatusAfastamento::INTERROMPIDO, 'Afastamento interrompido.');

            // Mantém permutas dos plantões já realizados (data <= interrupção) e remove os posteriores.
            app(PlantaoSubstituicaoService::class)->reverter($solicitacao->refresh(), $data);

            return $solicitacao->refresh();
        });
    }

    public function recalcularImpacto(AfastamentoSolicitacao $solicitacao): AfastamentoSolicitacao
    {
        $impacto = app(AfastamentoImpactScoreService::class)->calcular($solicitacao);
        $solicitacao->forceFill([
            'impacto_score' => $impacto['score'],
            'nivel_impacto' => $impacto['nivel'],
        ])->save();

        app(AfastamentoPrioridadeService::class)->atualizarSolicitacao($solicitacao);

        return $solicitacao->refresh();
    }

    private function mudarStatus(AfastamentoSolicitacao $solicitacao, StatusAfastamento $novo, string $acao, string $descricao): AfastamentoSolicitacao
    {
        $old = $solicitacao->status;
        $solicitacao->forceFill(['status' => $novo])->save();
        $this->historico($solicitacao, $acao, $old, $novo, $descricao);

        return $solicitacao->refresh();
    }

    private function validarSaldoParaDados(array $data, ?AfastamentoSolicitacao $solicitacao = null): void
    {
        $temp = $solicitacao
            ? $solicitacao->replicate()->forceFill($data)
            : new AfastamentoSolicitacao($data);

        $temp->exists = (bool) $solicitacao?->exists;
        $temp->setRelation('periodoAquisitivo', null);

        if (empty($data['periodo_aquisitivo_id'])) {
            throw ValidationException::withMessages([
                'periodo_aquisitivo_id' => 'Selecione um período aquisitivo adquirido com saldo disponível para solicitar este afastamento.',
            ]);
        }

        $temp->periodo_aquisitivo_id = (int) $data['periodo_aquisitivo_id'];
        $temp->load('periodoAquisitivo');

        if (! $temp->periodoAquisitivo
            || (int) $temp->periodoAquisitivo->user_id !== (int) $temp->user_id
            || $temp->periodoAquisitivo->tipo_afastamento !== $temp->tipo_afastamento) {
            throw ValidationException::withMessages([
                'periodo_aquisitivo_id' => 'O período aquisitivo selecionado não pertence ao servidor ou ao tipo de afastamento informado.',
            ]);
        }

        $this->validarRegrasParaSolicitacao($temp, $solicitacao);
        app(AfastamentoSaldoService::class)->validarSaldo($temp);
        $this->validarRegrasOperacionaisParaSolicitacao($temp);
    }

    private function validarRegrasParaSolicitacao(AfastamentoSolicitacao $solicitacao, ?AfastamentoSolicitacao $original = null): void
    {
        $regra = app(AfastamentoPeriodoAquisitivoService::class)->regra($solicitacao->tipo_afastamento);
        $dias = (int) ($solicitacao->dias_aprovados ?: $solicitacao->dias_solicitados);

        if ($dias <= 0) {
            throw ValidationException::withMessages([
                'dias_solicitados' => 'A quantidade de dias do afastamento deve ser maior que zero.',
            ]);
        }

        if ($dias > (int) $regra->dias_por_periodo) {
            throw ValidationException::withMessages([
                'dias_solicitados' => "A quantidade de dias solicitada não pode ultrapassar {$regra->dias_por_periodo} dias para este tipo de afastamento.",
            ]);
        }

        if (! $regra->permite_parcelamento && $dias !== (int) $regra->dias_por_periodo) {
            throw ValidationException::withMessages([
                'dias_solicitados' => "A regra deste tipo de afastamento não permite parcelamento. Solicite o período integral de {$regra->dias_por_periodo} dias.",
            ]);
        }

        if ($regra->permite_parcelamento && $dias < (int) $regra->dias_minimos_por_parcela) {
            throw ValidationException::withMessages([
                'dias_solicitados' => "Cada parcela deste tipo de afastamento deve ter no mínimo {$regra->dias_minimos_por_parcela} dias.",
            ]);
        }

        $parcelasUsadas = AfastamentoSolicitacao::query()
            ->whereKeyNot($original?->id ?? 0)
            ->where('user_id', $solicitacao->user_id)
            ->where('tipo_afastamento', $solicitacao->tipo_afastamento->value)
            ->when(
                $solicitacao->periodo_aquisitivo_id,
                fn ($query) => $query->where('periodo_aquisitivo_id', $solicitacao->periodo_aquisitivo_id),
            )
            ->whereIn('status', [
                StatusAfastamento::RASCUNHO->value,
                StatusAfastamento::SOLICITADO->value,
                StatusAfastamento::EM_ANALISE->value,
                StatusAfastamento::APROVADO->value,
                StatusAfastamento::CONCLUIDO->value,
            ])
            ->count();

        if (($parcelasUsadas + 1) > (int) $regra->quantidade_maxima_parcelas) {
            throw ValidationException::withMessages([
                'periodo_aquisitivo_id' => "A quantidade máxima de parcelas para este tipo de afastamento é {$regra->quantidade_maxima_parcelas}.",
            ]);
        }
    }

    private function validarRegrasOperacionaisParaSolicitacao(AfastamentoSolicitacao $solicitacao): void
    {
        $solicitacao->loadMissing('user');

        $conflitoCritico = collect(app(AfastamentoConflictService::class)->detectar($solicitacao))
            ->first(fn (array $conflito): bool => ($conflito['origem'] ?? null) === 'operacional'
                && ($conflito['nivel'] ?? null) === NivelImpacto::CRITICO->value);

        if ($conflitoCritico) {
            throw ValidationException::withMessages([
                'data_inicio' => ($conflitoCritico['mensagem'] ?? 'Regra operacional impede a solicitação.').' '.($conflitoCritico['sugestao'] ?? ''),
            ]);
        }
    }

    private function historico(AfastamentoSolicitacao $solicitacao, string $acao, mixed $old, mixed $new, string $descricao): void
    {
        AfastamentoHistorico::query()->create([
            'afastamento_solicitacao_id' => $solicitacao->id,
            'usuario_id' => Auth::id(),
            'acao' => $acao,
            'status_anterior' => $old instanceof StatusAfastamento ? $old->value : $old,
            'status_novo' => $new instanceof StatusAfastamento ? $new->value : $new,
            'descricao' => $descricao,
        ]);
    }

    private function isAdmin(): bool
    {
        $user = Auth::user();

        return (bool) $user && ($user->hasRole('admin') || $user->hasRole('super_admin'));
    }

    private function validarOperacionalAntesDaAprovacao(AfastamentoSolicitacao $solicitacao, bool $forcar): void
    {
        $funcao = $solicitacao->user?->funcao_operacional;
        $operacional = app(AfastamentoOperacionalService::class);

        $funcoesPlantao = [FuncaoOperacional::IPC_PLANTAO, FuncaoOperacional::EPC_PLANTAO];
        $funcoesExpediente = [FuncaoOperacional::IPC_EXPEDIENTE, FuncaoOperacional::EPC_EXPEDIENTE];

        if (in_array($funcao, $funcoesPlantao, true) && ! $operacional->coberturaAprovada($solicitacao) && ! $forcar) {
            throw ValidationException::withMessages([
                'cobertura' => "Afastamento de {$funcao->label()} exige cobertura aprovada ou exceção justificada por super_admin.",
            ]);
        }

        if (in_array($funcao, $funcoesExpediente, true)
            && $operacional->servidorEstaEmCobertura($solicitacao->user, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id)
            && ! $forcar) {
            throw ValidationException::withMessages([
                'cobertura' => "Servidor {$funcao->label()} está designado como cobertura de plantão neste período.",
            ]);
        }
    }
}
