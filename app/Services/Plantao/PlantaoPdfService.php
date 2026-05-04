<?php

namespace App\Services\Plantao;

use App\Models\PlantaoEscala;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class PlantaoPdfService
{
    public function dadosMensais(int $mes, int $ano): array
    {
        $this->validarProntoParaPdf($mes, $ano);

        $escalas = PlantaoEscala::query()
            ->with(['equipe.servidores.user.plantaoCqh', 'cqhGeral', 'permutas.servidorOriginal', 'permutas.servidorSubstituto'])
            ->whereYear('data_plantao', $ano)
            ->whereMonth('data_plantao', $mes)
            ->orderBy('data_plantao')
            ->get();
        $calendar = app(PlantaoCalendarService::class);

        return [
            'mes' => mb_strtoupper(Carbon::create($ano, $mes, 1)->translatedFormat('F')),
            'ano' => $ano,
            'emissao' => 'Confresa, '.now()->translatedFormat('d \d\e F \d\e Y').'.',
            'delegado_nome' => $this->delegadoAssinante()?->name ?? 'Delegado não definido',
            'brasao_mt' => public_path('images/pdf/brasao_mt.png'),
            'brasao_pjcmt' => public_path('images/pdf/brasao_pjcmt.png'),
            'linhas' => $escalas->map(function (PlantaoEscala $escala) use ($calendar): array {
                $membros = $calendar->membrosFinais($escala);
                $ipc = collect($membros['ipc'])->values();
                $epc = collect($membros['epc'])->values();

                return [
                    'dia' => $escala->data_plantao->format('d'),
                    'semana' => mb_strtoupper($escala->data_plantao->translatedFormat('l')),
                    'ipc1' => $ipc[0] ? $calendar->nomeCqh($ipc[0]) : '',
                    'ipc2' => $ipc[1] ? $calendar->nomeCqh($ipc[1]) : '',
                    'epc' => $epc[0] ? $calendar->nomeCqh($epc[0]) : '',
                    'cqh' => $escala->cqhGeral ? $calendar->nomeCqh($escala->cqhGeral) : '',
                    'cqh_derf' => (bool) ($escala->cqhGeral && method_exists($escala->cqhGeral, 'isDerf') && $escala->cqhGeral->isDerf()),
                    'horario' => '07h às 07h',
                ];
            })->all(),
        ];
    }

    public function validarProntoParaPdf(int $mes, int $ano): void
    {
        $inicio = Carbon::create($ano, $mes, 1);
        $diasDoMes = $inicio->daysInMonth;
        $escalas = PlantaoEscala::query()
            ->whereYear('data_plantao', $ano)
            ->whereMonth('data_plantao', $mes)
            ->get();

        if ($escalas->count() !== $diasDoMes) {
            throw ValidationException::withMessages([
                'plantao' => 'Gere a escala de plantão antes de gerar o PDF.',
            ]);
        }

        if ($escalas->contains(fn (PlantaoEscala $escala): bool => blank($escala->equipe_id))) {
            throw ValidationException::withMessages([
                'plantao' => 'Gere a escala de plantão antes de gerar o PDF.',
            ]);
        }

        if ($escalas->contains(fn (PlantaoEscala $escala): bool => blank($escala->cqh_geral_type) || blank($escala->cqh_geral_id))) {
            throw ValidationException::withMessages([
                'cqh' => 'Gere a escala CQH antes de gerar o PDF.',
            ]);
        }
    }

    private function delegadoAssinante(): ?User
    {
        return User::role('dpc')->orderBy('name')->first();
    }
}
