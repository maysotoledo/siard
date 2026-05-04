<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantaoHistorico extends Model
{
    protected $table = 'plantao_historicos';

    protected $fillable = ['escala_id', 'permuta_id', 'usuario_id', 'acao', 'descricao', 'dados'];

    protected $casts = ['dados' => 'array'];
}
