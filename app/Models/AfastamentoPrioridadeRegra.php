<?php

namespace App\Models;

use App\Enums\FuncaoOperacional;
use App\Enums\TipoAfastamento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AfastamentoPrioridadeRegra extends Model
{
    protected $table = 'afastamento_prioridade_regras';

    protected $fillable = [
        'nome',
        'tipo_afastamento',
        'funcao_operacional',
        'unidade_id',
        'usar_antiguidade_servico_publico',
        'usar_antiguidade_carreira',
        'usar_antiguidade_unidade',
        'peso_antiguidade_servico_publico',
        'peso_antiguidade_carreira',
        'peso_antiguidade_unidade',
        'peso_periodo_aquisitivo_mais_antigo',
        'peso_tempo_sem_gozo',
        'peso_saldo_vencido_ou_antigo',
        'peso_impacto_operacional',
        'ativo',
    ];

    protected $casts = [
        'tipo_afastamento' => TipoAfastamento::class,
        'funcao_operacional' => FuncaoOperacional::class,
        'usar_antiguidade_servico_publico' => 'boolean',
        'usar_antiguidade_carreira' => 'boolean',
        'usar_antiguidade_unidade' => 'boolean',
        'peso_antiguidade_servico_publico' => 'integer',
        'peso_antiguidade_carreira' => 'integer',
        'peso_antiguidade_unidade' => 'integer',
        'peso_periodo_aquisitivo_mais_antigo' => 'integer',
        'peso_tempo_sem_gozo' => 'integer',
        'peso_saldo_vencido_ou_antigo' => 'integer',
        'peso_impacto_operacional' => 'integer',
        'ativo' => 'boolean',
    ];

    public function scopeAtiva(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
