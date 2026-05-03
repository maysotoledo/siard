<?php

namespace App\Enums;

enum TipoAfastamento: string
{
    case FERIAS = 'ferias';
    case LICENCA_PREMIO = 'licenca_premio';

    public function label(): string
    {
        return match ($this) {
            self::FERIAS => 'Férias',
            self::LICENCA_PREMIO => 'Licença-prêmio',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
