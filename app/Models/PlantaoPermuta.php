<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PlantaoPermuta extends Model
{
    protected $table = 'plantao_permutas';

    protected $fillable = [
        'grupo_permuta',
        'escala_id',
        'escala_destino_id',
        'lado',
        'servidor_original_type',
        'servidor_original_id',
        'servidor_substituto_type',
        'servidor_substituto_id',
        'tipo_funcao',
        'data_plantao',
        'motivo',
        'autorizado_por',
        'autorizado_em',
    ];

    protected $casts = [
        'data_plantao' => 'date',
        'autorizado_em' => 'datetime',
    ];

    public function escala(): BelongsTo
    {
        return $this->belongsTo(PlantaoEscala::class, 'escala_id');
    }

    public function escalaDestino(): BelongsTo
    {
        return $this->belongsTo(PlantaoEscala::class, 'escala_destino_id');
    }

    public function servidorOriginal(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'servidor_original_type', 'servidor_original_id');
    }

    public function servidorSubstituto(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'servidor_substituto_type', 'servidor_substituto_id');
    }
}
