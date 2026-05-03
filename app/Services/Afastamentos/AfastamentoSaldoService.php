<?php

namespace App\Services\Afastamentos;

use App\Enums\StatusAfastamento;
use App\Enums\StatusPeriodoAquisitivo;
use App\Enums\TipoAfastamento;
use App\Models\AfastamentoPeriodoAquisitivo;
use App\Models\AfastamentoSolicitacao;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AfastamentoSaldoService
{
    public function saldoDisponivel(User|int $user, TipoAfastamento|string $tipo): int
    {
        $userId = $user instanceof User ? $user->id : $user;
        $tipoValue = $tipo instanceof TipoAfastamento ? $tipo->value : $tipo;

        return (int) AfastamentoPeriodoAquisitivo::query()
            ->where('user_id', $userId)
            ->where('tipo_afastamento', $tipoValue)
            ->whereDate('data_aquisicao', '<=', now()->toDateString())
            ->whereIn('status', $this->statusComSaldoDisponivel())
            ->sum('dias_disponiveis');
    }

    public function validarSaldo(AfastamentoSolicitacao $solicitacao): void
    {
        $dias = (int) ($solicitacao->dias_aprovados ?: $solicitacao->dias_solicitados);

        if ($solicitacao->periodoAquisitivo) {
            if (! $this->periodoTemDireitoAdquirido($solicitacao->periodoAquisitivo)) {
                throw ValidationException::withMessages([
                    'periodo_aquisitivo_id' => 'Período aquisitivo ainda não adquirido. O servidor ainda não possui direito disponível para este tipo de afastamento.',
                ]);
            }

            if ($solicitacao->periodoAquisitivo->dias_disponiveis < $dias) {
                throw ValidationException::withMessages([
                    'dias_solicitados' => 'Saldo insuficiente no período aquisitivo selecionado.',
                ]);
            }

            return;
        }

        if ($this->saldoDisponivel((int) $solicitacao->user_id, $solicitacao->tipo_afastamento) < $dias) {
            throw ValidationException::withMessages([
                'dias_solicitados' => 'Saldo insuficiente para este tipo de afastamento. Verifique se há período aquisitivo adquirido com saldo disponível.',
            ]);
        }
    }

    public function abater(AfastamentoSolicitacao $solicitacao): void
    {
        $dias = (int) ($solicitacao->dias_aprovados ?: $solicitacao->dias_solicitados);
        if ($dias <= 0) {
            return;
        }

        $periodos = $solicitacao->periodoAquisitivo
            ? collect([$solicitacao->periodoAquisitivo])
            : AfastamentoPeriodoAquisitivo::query()
                ->where('user_id', $solicitacao->user_id)
                ->where('tipo_afastamento', $solicitacao->tipo_afastamento->value)
                ->where('dias_disponiveis', '>', 0)
                ->whereDate('data_aquisicao', '<=', now()->toDateString())
                ->whereIn('status', $this->statusComSaldoDisponivel())
                ->orderBy('data_aquisicao')
                ->get();

        foreach ($periodos as $periodo) {
            if ($dias <= 0) {
                break;
            }

            $abatimento = min($dias, (int) $periodo->dias_disponiveis);
            $periodo->forceFill([
                'dias_usufruidos' => (int) $periodo->dias_usufruidos + $abatimento,
                'dias_disponiveis' => max(0, (int) $periodo->dias_disponiveis - $abatimento),
                'status' => $this->statusParaSaldo((int) $periodo->dias_direito, (int) $periodo->dias_usufruidos + $abatimento),
            ])->save();

            $dias -= $abatimento;
        }
    }

    public function devolver(AfastamentoSolicitacao $solicitacao, ?int $dias = null): void
    {
        $dias = $dias ?? (int) ($solicitacao->dias_aprovados ?: $solicitacao->dias_solicitados);
        if ($dias <= 0 || ! $solicitacao->periodoAquisitivo) {
            return;
        }

        $periodo = $solicitacao->periodoAquisitivo;
        $periodo->forceFill([
            'dias_usufruidos' => max(0, (int) $periodo->dias_usufruidos - $dias),
            'dias_disponiveis' => min((int) $periodo->dias_direito, (int) $periodo->dias_disponiveis + $dias),
            'status' => $this->statusParaSaldo((int) $periodo->dias_direito, max(0, (int) $periodo->dias_usufruidos - $dias)),
        ])->save();
    }

    private function statusParaSaldo(int $direito, int $usados): StatusPeriodoAquisitivo
    {
        if ($usados <= 0) {
            return StatusPeriodoAquisitivo::ADQUIRIDO;
        }

        if ($usados >= $direito) {
            return StatusPeriodoAquisitivo::USUFRUIDO;
        }

        return StatusPeriodoAquisitivo::PARCIALMENTE_USUFRUIDO;
    }

    private function periodoTemDireitoAdquirido(AfastamentoPeriodoAquisitivo $periodo): bool
    {
        return $periodo->data_aquisicao
            && $periodo->data_aquisicao->lte(now())
            && in_array($periodo->status?->value, $this->statusComSaldoDisponivel(), true);
    }

    private function statusComSaldoDisponivel(): array
    {
        return [
            StatusPeriodoAquisitivo::ADQUIRIDO->value,
            StatusPeriodoAquisitivo::PARCIALMENTE_USUFRUIDO->value,
            StatusPeriodoAquisitivo::APROVADO->value,
        ];
    }
}
