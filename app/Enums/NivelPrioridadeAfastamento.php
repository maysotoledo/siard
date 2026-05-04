<?php

namespace App\Enums;

enum NivelPrioridadeAfastamento: string
{
    case BAIXA = 'baixa';
    case MEDIA = 'media';
    case ALTA = 'alta';
    case MUITO_ALTA = 'muito_alta';

    public function label(): string
    {
        return match ($this) {
            self::BAIXA => 'Baixa',
            self::MEDIA => 'Média',
            self::ALTA => 'Alta',
            self::MUITO_ALTA => 'Muito alta',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BAIXA => 'gray',
            self::MEDIA => 'info',
            self::ALTA => 'warning',
            self::MUITO_ALTA => 'success',
        };
    }

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 80 => self::MUITO_ALTA,
            $score >= 55 => self::ALTA,
            $score >= 30 => self::MEDIA,
            default => self::BAIXA,
        };
    }
}
