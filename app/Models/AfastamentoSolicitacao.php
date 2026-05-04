<?php

namespace App\Models;

use App\Enums\NivelImpacto;
use App\Enums\StatusAfastamento;
use App\Enums\TipoAfastamento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AfastamentoSolicitacao extends Model
{
    protected $table = 'afastamento_solicitacoes';

    protected $fillable = [
        'user_id',
        'unidade_id',
        'periodo_aquisitivo_id',
        'tipo_afastamento',
        'data_inicio',
        'data_fim',
        'dias_solicitados',
        'dias_aprovados',
        'status',
        'impacto_score',
        'nivel_impacto',
        'prioridade_score',
        'prioridade_nivel',
        'prioridade_posicao',
        'prioridade_motivo',
        'prioridade_calculada_em',
        'justificativa_servidor',
        'justificativa_chefia',
        'aprovado_por',
        'aprovado_em',
        'indeferido_por',
        'indeferido_em',
        'cancelado_por',
        'cancelado_em',
        'observacao',
    ];

    protected $casts = [
        'tipo_afastamento' => TipoAfastamento::class,
        'status' => StatusAfastamento::class,
        'nivel_impacto' => NivelImpacto::class,
        'prioridade_score' => 'integer',
        'prioridade_posicao' => 'integer',
        'prioridade_calculada_em' => 'datetime',
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'dias_solicitados' => 'integer',
        'dias_aprovados' => 'integer',
        'impacto_score' => 'integer',
        'aprovado_em' => 'datetime',
        'indeferido_em' => 'datetime',
        'cancelado_em' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function periodoAquisitivo(): BelongsTo
    {
        return $this->belongsTo(AfastamentoPeriodoAquisitivo::class, 'periodo_aquisitivo_id');
    }

    public function historicos(): HasMany
    {
        return $this->hasMany(AfastamentoHistorico::class, 'afastamento_solicitacao_id');
    }

    public function interrupcoes(): HasMany
    {
        return $this->hasMany(AfastamentoInterrupcao::class, 'afastamento_solicitacao_id');
    }

    public function coberturasPlantao(): HasMany
    {
        return $this->hasMany(AfastamentoCoberturaPlantao::class, 'afastamento_solicitacao_id');
    }

    public function scopeAtivasNoPeriodo(Builder $query, mixed $inicio, mixed $fim): Builder
    {
        return $query
            ->whereIn('status', [StatusAfastamento::APROVADO->value, StatusAfastamento::INTERROMPIDO->value])
            ->whereDate('data_inicio', '<=', $fim)
            ->whereDate('data_fim', '>=', $inicio);
    }

    public function scopePendentes(Builder $query): Builder
    {
        return $query->whereIn('status', [
            StatusAfastamento::SOLICITADO->value,
            StatusAfastamento::EM_ANALISE->value,
        ]);
    }
}
