<?php

namespace App\Services\Plantao;

use App\Models\PlantaoEquipe;
use App\Models\PlantaoEscala;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlantaoEscalaService
{
    public function gerarEscalaMensal(int $mes, int $ano, int $equipeInicialId, bool $force = false, bool $dryRun = false): array
    {
        $inicio = Carbon::create($ano, $mes, 1)->startOfDay();
        $fim = $inicio->copy()->endOfMonth();
        $equipes = PlantaoEquipe::query()->where('ativo', true)->orderBy('id')->get();
        $ids = $equipes->pluck('id')->values();
        $startIndex = max(0, $ids->search($equipeInicialId) ?: 0);
        $summary = ['criados' => 0, 'atualizados' => 0, 'ignorados' => 0, 'dry_run' => $dryRun];

        return DB::transaction(function () use ($inicio, $fim, $ids, $startIndex, $force, $dryRun, $summary): array {
            for ($date = $inicio->copy(), $i = 0; $date->lte($fim); $date->addDay(), $i++) {
                $equipeId = $ids[($startIndex + $i) % max(1, $ids->count())] ?? null;
                $existing = PlantaoEscala::query()->whereDate('data_plantao', $date->toDateString())->first();

                if ($existing && ! $force) {
                    $summary['ignorados']++;
                    continue;
                }

                if (! $dryRun) {
                    PlantaoEscala::query()->updateOrCreate(
                        ['data_plantao' => $date->toDateString()],
                        [
                            'equipe_id' => $equipeId,
                            'horario_inicio' => '07:00:00',
                            'horario_fim' => '07:00:00',
                            'status' => $existing ? 'alterada' : 'prevista',
                            'criado_por' => Auth::id(),
                        ],
                    );
                }

                $existing ? $summary['atualizados']++ : $summary['criados']++;
            }

            return $summary;
        });
    }
}
