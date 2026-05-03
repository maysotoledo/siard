<?php

namespace App\Models;

use App\Enums\FuncaoOperacional;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AfastamentoCoberturaPlantao extends Model
{
    protected $table = 'afastamento_coberturas_plantao';

    protected $fillable = [
        'afastamento_solicitacao_id',
        'servidor_plantao_afastado_id',
        'servidor_cobertura_id',
        'funcao_origem',
        'funcao_destino',
        'data_inicio',
        'data_fim',
        'status',
        'aprovado_por',
        'aprovado_em',
        'observacao',
    ];

    protected $casts = [
        'funcao_origem' => FuncaoOperacional::class,
        'funcao_destino' => FuncaoOperacional::class,
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'aprovado_em' => 'datetime',
    ];

    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(AfastamentoSolicitacao::class, 'afastamento_solicitacao_id');
    }

    public function servidorPlantaoAfastado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'servidor_plantao_afastado_id');
    }

    public function servidorCobertura(): BelongsTo
    {
        return $this->belongsTo(User::class, 'servidor_cobertura_id');
    }

    public function aprovadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por');
    }
}
