<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantaoCqhExterno extends Model
{
    protected $table = 'plantao_cqh_externos';

    protected $fillable = [
        'nome',
        'unidade_operacional',
        'nome_calendario',
        'telefone',
        'ordem',
        'apto_cqh',
        'ativo',
        'observacao',
    ];

    protected $casts = [
        'apto_cqh' => 'boolean',
        'ativo' => 'boolean',
    ];

    public function isDerf(): bool
    {
        return $this->unidade_operacional === 'DERF_CONFRESA';
    }
}
