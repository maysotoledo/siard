<?php

namespace App\Enums;

enum PlantaoStatus: string
{
    case PREVISTA = 'prevista';
    case PUBLICADA = 'publicada';
    case ALTERADA = 'alterada';
    case CANCELADA = 'cancelada';
    case LOCKED = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::PREVISTA => 'Prevista',
            self::PUBLICADA => 'Publicada',
            self::ALTERADA => 'Alterada',
            self::CANCELADA => 'Cancelada',
            self::LOCKED => 'Travada',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
