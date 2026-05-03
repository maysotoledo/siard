<?php

namespace App\Enums;

enum NivelImpacto: string
{
    case BAIXO = 'baixo';
    case MODERADO = 'moderado';
    case ALTO = 'alto';
    case CRITICO = 'critico';

    public function label(): string
    {
        return match ($this) {
            self::BAIXO => 'Baixo',
            self::MODERADO => 'Moderado',
            self::ALTO => 'Alto',
            self::CRITICO => 'Crítico',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BAIXO => 'success',
            self::MODERADO => 'warning',
            self::ALTO => 'danger',
            self::CRITICO => 'danger',
        };
    }

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score <= 25 => self::BAIXO,
            $score <= 50 => self::MODERADO,
            $score <= 75 => self::ALTO,
            default => self::CRITICO,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
