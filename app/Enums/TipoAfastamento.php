<?php

namespace App\Enums;

enum TipoAfastamento: string
{
    case FERIAS = 'ferias';
    case LICENCA_PREMIO = 'licenca_premio';
    case ATESTADO = 'atestado';

    public function label(): string
    {
        return match ($this) {
            self::FERIAS => 'Férias',
            self::LICENCA_PREMIO => 'Licença-prêmio',
            self::ATESTADO => 'Atestado',
        };
    }

    /**
     * Tipos que possuem período aquisitivo (acúmulo de tempo de serviço).
     * Atestado é registrado diretamente, sem período.
     */
    public function temPeriodoAquisitivo(): bool
    {
        return $this !== self::ATESTADO;
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
