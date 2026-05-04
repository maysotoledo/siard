<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantaoDelegadoEscala extends Model
{
    protected $table = 'plantao_delegados_escalas';

    protected $fillable = [
        'data_plantao',
        'nome_delegado',
        'unidade_delegado',
        'contato',
        'horario',
        'regionalizado',
        'origem_pdf',
        'dados_extraidos',
        'importado_por',
    ];

    protected $casts = [
        'data_plantao' => 'date',
        'regionalizado' => 'boolean',
        'dados_extraidos' => 'array',
    ];

    public function plantaoEscala(): BelongsTo
    {
        return $this->belongsTo(PlantaoEscala::class, 'data_plantao', 'data_plantao');
    }

    public function importador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'importado_por');
    }
}
