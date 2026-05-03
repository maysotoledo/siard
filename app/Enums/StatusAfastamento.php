<?php

namespace App\Enums;

enum StatusAfastamento: string
{
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
            self::RASCUNHO => 'Rascunho',
            self::SOLICITADO => 'Solicitado',
            self::EM_ANALISE => 'Em análise',
            self::APROVADO => 'Aprovado',
            self::INDEFERIDO => 'Indeferido',
            self::CANCELADO => 'Cancelado',
            self::INTERROMPIDO => 'Interrompido',
            self::CONCLUIDO => 'Concluído',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RASCUNHO => 'gray',
            self::SOLICITADO, self::EM_ANALISE => 'warning',
            self::APROVADO, self::CONCLUIDO => 'success',
            self::INDEFERIDO, self::CANCELADO, self::INTERROMPIDO => 'danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
