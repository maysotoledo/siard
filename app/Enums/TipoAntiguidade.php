<?php

namespace App\Enums;

enum TipoAntiguidade: string
{
    case SERVICO_PUBLICO = 'servico_publico';
    case CARREIRA = 'carreira';
    case UNIDADE = 'unidade';

    public function label(): string
    {
        return match ($this) {
            self::SERVICO_PUBLICO => 'Serviço público',
            self::CARREIRA => 'Carreira',
            self::UNIDADE => 'Unidade',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $tipo): array => [$tipo->value => $tipo->label()])
            ->all();
    }
}
