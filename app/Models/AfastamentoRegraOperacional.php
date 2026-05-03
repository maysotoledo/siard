<?php

namespace App\Models;

use App\Enums\FuncaoOperacional;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AfastamentoRegraOperacional extends Model
{
    protected $table = 'afastamento_regras_operacionais';

    protected $fillable = [
        'unidade_id',
        'funcao_operacional',
        'grupo_operacional',
        'minimo_disponivel',
        'prioridade_operacional',
        'permite_cobertura_por_funcao',
        'cargo',
        'funcao',
        'setor',
        'minimo_por_dia',
        'maximo_afastados_simultaneos',
        'dias_criticos',
        'ativo',
    ];

    protected $casts = [
        'funcao_operacional' => FuncaoOperacional::class,
        'permite_cobertura_por_funcao' => 'array',
        'minimo_disponivel' => 'integer',
        'prioridade_operacional' => 'boolean',
        'dias_criticos' => 'array',
        'minimo_por_dia' => 'integer',
        'maximo_afastados_simultaneos' => 'integer',
        'ativo' => 'boolean',
    ];

    public function scopeAtiva(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
