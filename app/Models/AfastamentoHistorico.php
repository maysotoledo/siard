<?php

namespace App\Models;

use App\Enums\StatusAfastamento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AfastamentoHistorico extends Model
{
    protected $table = 'afastamento_historicos';

    protected $fillable = [
        'afastamento_solicitacao_id',
        'usuario_id',
        'acao',
        'status_anterior',
        'status_novo',
        'descricao',
        'dados',
    ];

    protected $casts = [
        'status_anterior' => StatusAfastamento::class,
        'status_novo' => StatusAfastamento::class,
        'dados' => 'array',
    ];

    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(AfastamentoSolicitacao::class, 'afastamento_solicitacao_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
