<?php

namespace App\Enums;

enum StatusPeriodoAquisitivo: string
{
    case EM_AQUISICAO = 'em_aquisicao';
    case ADQUIRIDO = 'adquirido';
    case PARCIALMENTE_USUFRUIDO = 'parcialmente_usufruido';
    case USUFRUIDO = 'usufruido';
    case VENCIDO = 'vencido';
    case RASCUNHO = 'rascunho';
    case SOLICITADO = 'solicitado';
    case EM_ANALISE = 'em_analise';
    case APROVADO = 'aprovado';
    case INDEFERIDO = 'indeferido';
    case CANCELADO = 'cancelado';
    case INTERROMPIDO = 'interrompido';
    case CONCLUIDO = 'concluido';

    public function label(): string
    {
        return match ($this) {
            self::EM_AQUISICAO => 'Em aquisição',
            self::ADQUIRIDO => 'Adquirido',
            self::PARCIALMENTE_USUFRUIDO => 'Parcialmente usufruído',
            self::USUFRUIDO => 'Usufruído',
            self::VENCIDO => 'Vencido',
            self::RASCUNHO => 'Rascunho',
            self::SOLICITADO => 'Solicitado',
            self::EM_ANALISE => 'Em análise',
            self::APROVADO => 'Adquirido',
            self::INDEFERIDO => 'Indeferido',
            self::CANCELADO => 'Cancelado',
            self::INTERROMPIDO => 'Interrompido',
            self::CONCLUIDO => 'Concluído',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EM_AQUISICAO => 'gray',
            self::ADQUIRIDO => 'success',
            self::PARCIALMENTE_USUFRUIDO => 'warning',
            self::USUFRUIDO => 'info',
            self::VENCIDO => 'danger',
            self::RASCUNHO => 'gray',
            self::SOLICITADO => 'info',
            self::EM_ANALISE => 'warning',
            self::APROVADO => 'success',
            self::INDEFERIDO => 'danger',
            self::CANCELADO => 'danger',
            self::INTERROMPIDO => 'danger',
            self::CONCLUIDO => 'success',
        };
    }

    public static function options(): array
    {
        return collect([
            self::EM_AQUISICAO,
            self::ADQUIRIDO,
            self::PARCIALMENTE_USUFRUIDO,
            self::USUFRUIDO,
            self::VENCIDO,
        ])
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
