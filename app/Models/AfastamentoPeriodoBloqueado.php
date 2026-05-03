<?php

namespace App\Models;

use App\Enums\TipoAfastamento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AfastamentoPeriodoBloqueado extends Model
{
    protected $table = 'afastamento_periodos_bloqueados';

    protected $fillable = [
        'unidade_id',
        'tipo_afastamento',
        'data_inicio',
        'data_fim',
        'motivo',
        'bloqueio_total',
        'funcoes_afetadas',
        'ativo',
    ];

    protected $casts = [
        'tipo_afastamento' => TipoAfastamento::class,
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'bloqueio_total' => 'boolean',
        'funcoes_afetadas' => 'array',
        'ativo' => 'boolean',
    ];

    public function scopeAtivo(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
