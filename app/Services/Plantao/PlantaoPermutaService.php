<?php

namespace App\Services\Plantao;

use App\Models\PlantaoEscala;
use App\Models\PlantaoPermuta;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PlantaoPermutaService
{
    public function permutarEntreDias(
        int $escalaOrigemId,
        int $escalaDestinoId,
        string|int $servidorOriginalId,
        string|int $servidorDestinoId,
        string $tipoFuncao,
        ?string $motivo = null,
    ): PlantaoPermuta {
        $origem = PlantaoEscala::query()->findOrFail($escalaOrigemId);
        $destino = PlantaoEscala::query()->findOrFail($escalaDestinoId);
        [$originalType, $originalId] = app(PlantaoCqhService::class)->parsePessoaKey($servidorOriginalId);
        [$destinoType, $destinoId] = app(PlantaoCqhService::class)->parsePessoaKey($servidorDestinoId);
        $grupo = (string) Str::uuid();

        if ($tipoFuncao === 'cqh_geral') {
            $origem->forceFill([
                'cqh_geral_type' => $destinoType,
                'cqh_geral_id' => $destinoId,
                'status' => 'alterada',
            ])->save();

            $destino->forceFill([
                'cqh_geral_type' => $originalType,
                'cqh_geral_id' => $originalId,
                'status' => 'alterada',
            ])->save();
        }

        $permutaOrigem = $this->registrarPermuta($origem, $originalType, $originalId, $destinoType, $destinoId, $tipoFuncao, $motivo, 'Permuta registrada.', $grupo, $destino->id, 'origem');
        $this->registrarPermuta($destino, $destinoType, $destinoId, $originalType, $originalId, $tipoFuncao, $motivo, 'Permuta de destino registrada.', $grupo, $origem->id, 'destino');

        return $permutaOrigem;
    }

    public function permutar(int $escalaId, string|int $servidorOriginalId, string|int $servidorSubstitutoId, string $tipoFuncao, ?string $motivo = null): PlantaoPermuta
    {
        $escala = PlantaoEscala::query()->with(['equipe.servidores.user', 'cqhGeral'])->findOrFail($escalaId);
        $originalType = User::class;
        $substitutoType = User::class;
        $originalId = 0;
        $substitutoId = 0;

        if ($tipoFuncao === 'cqh_geral') {
            [$originalType, $originalId] = app(PlantaoCqhService::class)->parsePessoaKey($servidorOriginalId);
            [$substitutoType, $substitutoId] = app(PlantaoCqhService::class)->parsePessoaKey($servidorSubstitutoId);

            $escala->forceFill([
                'cqh_geral_type' => $substitutoType,
                'cqh_geral_id' => $substitutoId,
                'status' => 'alterada',
            ])->save();
        } else {
            [$originalType, $originalId] = $this->parsePessoaKey($servidorOriginalId);
            [$substitutoType, $substitutoId] = $this->parsePessoaKey($servidorSubstitutoId);
        }

        return $this->registrarPermuta($escala, $originalType, $originalId, $substitutoType, $substitutoId, $tipoFuncao, $motivo);
    }

    private function registrarPermuta(
        PlantaoEscala $escala,
        string $originalType,
        int $originalId,
        string $substitutoType,
        int $substitutoId,
        string $tipoFuncao,
        ?string $motivo = null,
        string $descricao = 'Permuta registrada.',
        ?string $grupo = null,
        ?int $escalaDestinoId = null,
        ?string $lado = null,
    ): PlantaoPermuta {
        $permuta = PlantaoPermuta::query()->create([
            'grupo_permuta' => $grupo,
            'escala_id' => $escala->id,
            'escala_destino_id' => $escalaDestinoId,
            'lado' => $lado,
            'servidor_original_type' => $originalType,
            'servidor_original_id' => $originalId,
            'servidor_substituto_type' => $substitutoType,
            'servidor_substituto_id' => $substitutoId,
            'tipo_funcao' => $tipoFuncao,
            'data_plantao' => $escala->data_plantao,
            'motivo' => $motivo,
            'autorizado_por' => Auth::id(),
            'autorizado_em' => now(),
        ]);

        $escala->forceFill(['status' => 'alterada'])->save();
        app(PlantaoHistoricoService::class)->registrar($escala, 'permuta', $descricao, [
            'original_type' => $originalType,
            'original' => $originalId,
            'substituto_type' => $substitutoType,
            'substituto' => $substitutoId,
            'tipo' => $tipoFuncao,
        ], $permuta);

        return $permuta;
    }

    private function parsePessoaKey(string|int $value): array
    {
        return app(PlantaoCqhService::class)->parsePessoaKey($value);
    }
}
