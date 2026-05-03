<?php

namespace App\Models;

use App\Enums\StatusPeriodoAquisitivo;
use App\Enums\TipoAfastamento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AfastamentoPeriodoAquisitivo extends Model
{
    protected $table = 'afastamento_periodos_aquisitivos';

    protected $fillable = [
        'user_id',
        'tipo_afastamento',
        'data_inicio',
        'data_fim',
        'data_aquisicao',
        'dias_direito',
        'dias_usufruidos',
        'dias_disponiveis',
        'status',
        'gerado_automaticamente',
        'observacao',
    ];

    protected $casts = [
        'tipo_afastamento' => TipoAfastamento::class,
        'status' => StatusPeriodoAquisitivo::class,
        'gerado_automaticamente' => 'boolean',
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'data_aquisicao' => 'date',
        'dias_direito' => 'integer',
        'dias_usufruidos' => 'integer',
        'dias_disponiveis' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function solicitacoes(): HasMany
    {
        return $this->hasMany(AfastamentoSolicitacao::class, 'periodo_aquisitivo_id');
    }

    public function scopeDoTipo(Builder $query, TipoAfastamento|string $tipo): Builder
    {
        return $query->where('tipo_afastamento', $tipo instanceof TipoAfastamento ? $tipo->value : $tipo);
    }
}
