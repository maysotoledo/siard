<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PixelModuleSetting extends Model
{
    protected $fillable = [
        'payment_enabled',
        'manutencao_ativa',
        'manutencao_prevista',
    ];

    protected function casts(): array
    {
        return [
            'payment_enabled'   => 'boolean',
            'manutencao_ativa'  => 'boolean',
            'manutencao_prevista' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'payment_enabled'  => true,
            'manutencao_ativa' => false,
        ]);
    }

    public static function isPaymentEnabled(): bool
    {
        return static::current()->payment_enabled;
    }

    public static function isManutencaoAtiva(): bool
    {
        return (bool) static::current()->manutencao_ativa;
    }
}
