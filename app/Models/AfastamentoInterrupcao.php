<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AfastamentoInterrupcao extends Model
{
    protected $table = 'afastamento_interrupcoes';

    protected $fillable = [
        'afastamento_solicitacao_id',
        'interrompido_por',
        'data_interrupcao',
        'motivo',
        'dias_restantes',
        'saldo_devolvido',
    ];

    protected $casts = [
        'data_interrupcao' => 'date',
        'dias_restantes' => 'integer',
        'saldo_devolvido' => 'boolean',
    ];

    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(AfastamentoSolicitacao::class, 'afastamento_solicitacao_id');
    }

    public function interrompidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interrompido_por');
    }
}
