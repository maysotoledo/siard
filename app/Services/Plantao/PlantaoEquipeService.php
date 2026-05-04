<?php

namespace App\Services\Plantao;

use App\Models\PlantaoEquipe;
use Illuminate\Validation\ValidationException;

class PlantaoEquipeService
{
    public function validarEquipe(PlantaoEquipe $equipe): void
    {
        $servidores = $equipe->servidores()->with('user.roles')->where('ativo', true)->get();

        if ($servidores->pluck('user_id')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['servidores' => 'A equipe não pode ter servidor duplicado.']);
        }

        if ($servidores->where('funcao_plantao', 'ipc_plantao')->count() !== 2) {
            throw ValidationException::withMessages(['servidores' => 'A equipe deve ter exatamente 2 IPC plantonistas.']);
        }

        if ($servidores->where('funcao_plantao', 'epc_plantao')->count() !== 1) {
            throw ValidationException::withMessages(['servidores' => 'A equipe deve ter exatamente 1 EPC plantonista.']);
        }

        foreach ($servidores as $servidor) {
            if (! $servidor->user?->hasRole($servidor->funcao_plantao)) {
                throw ValidationException::withMessages([
                    'servidores' => 'Servidor sem role compatível com a função informada na equipe.',
                ]);
            }
        }
    }
}
