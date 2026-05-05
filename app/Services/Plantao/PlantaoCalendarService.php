<?php

namespace App\Services\Plantao;

use App\Models\PlantaoEscala;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class PlantaoCalendarService
{
    public function eventos(?Carbon $inicio = null, ?Carbon $fim = null): array
    {
        return PlantaoEscala::query()
            ->with(['equipe.servidores.user.plantaoCqh', 'cqhGeral', 'delegadoDelta', 'permutas.servidorOriginal', 'permutas.servidorSubstituto'])
            ->when($inicio, fn ($query) => $query->whereDate('data_plantao', '>=', $inicio->toDateString()))
            ->when($fim, fn ($query) => $query->whereDate('data_plantao', '<=', $fim->toDateString()))
            ->orderBy('data_plantao')
            ->get()
            ->map(function (PlantaoEscala $escala): array {
                $linhas = $this->linhasCalendario($escala);

                return [
                    'id' => $escala->id,
                    'title' => $this->titulo($escala),
                    'start' => $escala->data_plantao->toDateString(),
                    'allDay' => true,
                    'backgroundColor' => '#ffffff',
                    'borderColor' => '#d1d5db',
                    'textColor' => '#111827',
                    'extendedProps' => [
                        'ipc' => $linhas['ipc'],
                        'epc' => $linhas['epc'],
                        'cqh' => $linhas['cqh'],
                        'dpc' => $linhas['dpc'],
                        'dpcContato' => $escala->delegadoDelta?->contato,
                    ],
                ];
            })
            ->all();
    }

    public function linhasCalendario(PlantaoEscala $escala): array
    {
        $escala->loadMissing(['equipe.servidores.user', 'cqhGeral', 'delegadoDelta', 'permutas.servidorOriginal', 'permutas.servidorSubstituto']);
        $ipc = $escala->equipe?->servidores->where('ativo', true)->where('funcao_plantao', 'ipc_plantao')->pluck('user')->filter()->values() ?? collect();
        $epc = $escala->equipe?->servidores->where('ativo', true)->where('funcao_plantao', 'epc_plantao')->pluck('user')->filter()->values() ?? collect();

        return [
            'ipc' => $this->linhasPorFuncao($ipc, $escala, 'ipc_plantao'),
            'epc' => $this->linhasPorFuncao($epc, $escala, 'epc_plantao')->first(),
            'cqh' => $this->linhaCqh($escala),
            'dpc' => $this->linhaDpc($escala->delegadoDelta?->nome_delegado),
        ];
    }

    public function titulo(PlantaoEscala $escala): string
    {
        $membros = $this->membrosFinais($escala);
        $ipc = collect($membros['ipc'])->map(fn (Model $pessoa): string => $this->nomePessoa($pessoa))->filter()->values();
        $epcPessoa = collect($membros['epc'])->first();
        $epc = $epcPessoa ? $this->nomePessoa($epcPessoa) : '-';
        $dpc = $escala->delegadoDelta?->nome_delegado ?: '-';
        $cqh = $escala->cqhGeral ? $this->nomeCqh($escala->cqhGeral) : '-';

        return 'PLANTÃO: '.($ipc->isNotEmpty() ? $ipc->implode(', ') : '-').' | EPC: '.$epc.' | DPC: '.$dpc.' | CQH: '.$cqh;
    }

    public function membrosFinais(PlantaoEscala $escala): array
    {
        $escala->loadMissing(['equipe.servidores.user', 'permutas.servidorOriginal', 'permutas.servidorSubstituto']);
        $ipc = $escala->equipe?->servidores->where('ativo', true)->where('funcao_plantao', 'ipc_plantao')->pluck('user')->filter()->values() ?? collect();
        $epc = $escala->equipe?->servidores->where('ativo', true)->where('funcao_plantao', 'epc_plantao')->pluck('user')->filter()->values() ?? collect();

        foreach ($escala->permutas as $permuta) {
            if ($permuta->tipo_funcao === 'ipc_plantao') {
                $ipc = $this->aplicarTroca($ipc, $permuta);
            }
            if ($permuta->tipo_funcao === 'epc_plantao') {
                $epc = $this->aplicarTroca($epc, $permuta);
            }
        }

        return ['ipc' => $ipc->all(), 'epc' => $epc->all()];
    }

    public function servidorEstaNoPlantao(PlantaoEscala $escala, int $userId): bool
    {
        $membros = $this->membrosFinais($escala);

        return collect([...$membros['ipc'], ...$membros['epc']])->contains(fn (Model $pessoa): bool => $pessoa->id === $userId);
    }

    public function nomeCqh(Model $pessoa, bool $curto = false): string
    {
        return app(PlantaoCqhService::class)->nomePessoa($pessoa, $curto);
    }

    private function nomePessoa(Model $pessoa): string
    {
        return app(PlantaoCqhService::class)->nomePessoa($pessoa);
    }

    private function nomePessoaCurto(Model $pessoa): string
    {
        return app(PlantaoCqhService::class)->nomePessoa($pessoa, true);
    }

    private function aplicarTroca($membros, $permuta)
    {
        return $membros
            ->map(function (Model $pessoa) use ($permuta): ?Model {
                if ($this->mesmaPessoa($pessoa, $permuta->servidor_original_type, (int) $permuta->servidor_original_id)) {
                    return $permuta->servidorSubstituto;
                }

                if ($this->mesmaPessoa($pessoa, $permuta->servidor_substituto_type, (int) $permuta->servidor_substituto_id)) {
                    return $permuta->servidorOriginal;
                }

                return $pessoa;
            })
            ->filter()
            ->values();
    }

    private function mesmaPessoa(Model $pessoa, ?string $type, int $id): bool
    {
        return $pessoa::class === $type && (int) $pessoa->id === $id;
    }

    private function linhasPorFuncao($membros, PlantaoEscala $escala, string $tipoFuncao)
    {
        return $membros
            ->map(function (Model $pessoa) use ($escala, $tipoFuncao): array {
                $atual = $pessoa;
                $permutado = false;

                foreach ($escala->permutas->where('tipo_funcao', $tipoFuncao) as $permuta) {
                    if ($this->mesmaPessoa($pessoa, $permuta->servidor_original_type, (int) $permuta->servidor_original_id)) {
                        $atual = $permuta->servidorSubstituto ?: $atual;
                        $permutado = true;
                    }
                }

                return $this->linhaPessoa($pessoa, $atual, $permutado);
            })
            ->values();
    }

    private function linhaCqh(PlantaoEscala $escala): ?array
    {
        if (! $escala->cqhGeral) {
            return null;
        }

        $atual = $escala->cqhGeral;
        $original = $atual;
        $permutado = false;
        $permuta = $escala->permutas->where('tipo_funcao', 'cqh_geral')->last();

        if ($permuta?->servidorOriginal && $permuta->servidorSubstituto) {
            $original = $permuta->servidorOriginal;
            $atual = $permuta->servidorSubstituto;
            $permutado = true;
        }

        return $this->linhaPessoa($original, $atual, $permutado);
    }

    private function linhaPessoa(Model $original, ?Model $atual, bool $permutado): array
    {
        return [
            'original' => $this->nomePessoaCurto($original),
            'atual' => $atual ? $this->nomePessoaCurto($atual) : null,
            'permutado' => $permutado,
        ];
    }

    private function linhaDpc(?string $nome): ?array
    {
        if (! $nome) {
            return null;
        }

        $nomeCurto = collect(preg_split('/\s+/', trim($nome)) ?: [])
            ->filter()
            ->take(3)
            ->implode(' ');

        return [
            'original' => $nomeCurto,
            'atual' => $nomeCurto,
            'permutado' => false,
        ];
    }
}
