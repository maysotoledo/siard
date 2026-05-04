<?php

namespace App\Services\Plantao;

use App\Models\PlantaoEscala;
use App\Models\PlantaoHistorico;
use App\Models\PlantaoPermuta;
use Illuminate\Support\Facades\Auth;

class PlantaoHistoricoService
{
    public function registrar(?PlantaoEscala $escala, string $acao, string $descricao, array $dados = [], ?PlantaoPermuta $permuta = null): void
    {
        PlantaoHistorico::query()->create([
            'escala_id' => $escala?->id,
            'permuta_id' => $permuta?->id,
            'usuario_id' => Auth::id(),
            'acao' => $acao,
            'descricao' => $descricao,
            'dados' => $dados,
        ]);
    }
}
