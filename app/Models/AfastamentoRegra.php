<?php

namespace App\Models;

use App\Enums\TipoAfastamento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AfastamentoRegra extends Model
{
    protected $fillable = [
        'tipo_afastamento',
        'nome',
        'dias_por_periodo',
        'meses_para_aquisicao',
        'permite_parcelamento',
        'quantidade_maxima_parcelas',
        'dias_minimos_por_parcela',
        'exige_aprovacao_chefia',
        'afeta_efetivo_minimo',
        'permite_interrupcao',
        'permite_cancelamento_apos_inicio',
        'devolve_saldo_ao_interromper',
        'ativo',
    ];

    protected $casts = [
        'tipo_afastamento' => TipoAfastamento::class,
        'dias_por_periodo' => 'integer',
        'meses_para_aquisicao' => 'integer',
        'permite_parcelamento' => 'boolean',
        'quantidade_maxima_parcelas' => 'integer',
        'dias_minimos_por_parcela' => 'integer',
        'exige_aprovacao_chefia' => 'boolean',
        'afeta_efetivo_minimo' => 'boolean',
        'permite_interrupcao' => 'boolean',
        'permite_cancelamento_apos_inicio' => 'boolean',
        'devolve_saldo_ao_interromper' => 'boolean',
        'ativo' => 'boolean',
    ];

    public function scopeAtiva(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
