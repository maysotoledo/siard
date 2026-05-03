<?php

namespace App\Filament\Widgets;

use App\Enums\FuncaoOperacional;
use App\Enums\StatusAfastamento;
use App\Enums\TipoAfastamento;
use App\Models\AfastamentoCoberturaPlantao;
use App\Models\AfastamentoPeriodoAquisitivo;
use App\Models\AfastamentoSolicitacao;
use App\Services\Afastamentos\AfastamentoOperacionalService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AfastamentosStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $today = now()->toDateString();
        $ativos = AfastamentoSolicitacao::query()
            ->where('status', StatusAfastamento::APROVADO->value)
            ->whereDate('data_inicio', '<=', $today)
            ->whereDate('data_fim', '>=', $today);
        $operacional = app(AfastamentoOperacionalService::class);
        $ipcExpedienteDisponiveis = $operacional->disponiveisDaFuncao(FuncaoOperacional::IPC_EXPEDIENTE, $today, $today);
        $ipcPlantaoDisponiveis = $operacional->disponiveisDaFuncao(FuncaoOperacional::IPC_PLANTAO, $today, $today);
        $minimoIpcExpediente = $operacional->minimoDisponivel(FuncaoOperacional::IPC_EXPEDIENTE);

        return [
            Stat::make('Afastamentos ativos hoje', (clone $ativos)->count()),
            Stat::make('Férias ativas hoje', (clone $ativos)->where('tipo_afastamento', TipoAfastamento::FERIAS->value)->count()),
            Stat::make('Licenças-prêmio ativas hoje', (clone $ativos)->where('tipo_afastamento', TipoAfastamento::LICENCA_PREMIO->value)->count()),
            Stat::make('Solicitações pendentes', AfastamentoSolicitacao::query()->pendentes()->count()),
            Stat::make('Férias vencidas/adquiridas', $this->periodosAdquiridos(TipoAfastamento::FERIAS, $today)->count()),
            Stat::make('Licença-prêmio adquirida', $this->periodosAdquiridos(TipoAfastamento::LICENCA_PREMIO, $today)->count()),
            Stat::make('IPC expediente disponíveis', $ipcExpedienteDisponiveis)
                ->color($ipcExpedienteDisponiveis < $minimoIpcExpediente ? 'danger' : 'success'),
            Stat::make('IPC plantão disponíveis', $ipcPlantaoDisponiveis),
            Stat::make('IPC expediente deslocados para plantão', AfastamentoCoberturaPlantao::query()
                ->where('status', 'aprovada')
                ->whereDate('data_inicio', '<=', $today)
                ->whereDate('data_fim', '>=', $today)
                ->count()),
            Stat::make('Afastamentos do expediente', (clone $ativos)->whereHas('user.roles', fn ($query) => $query->whereIn('name', ['ipc', 'epc', 'cartorio_central', 'dpc']))->count()),
            Stat::make('Afastamentos do plantão', (clone $ativos)->whereHas('user.roles', fn ($query) => $query->whereIn('name', ['ipc_plantao', 'epc_plantao']))->count()),
            Stat::make('Risco de déficit no expediente', $ipcExpedienteDisponiveis < $minimoIpcExpediente ? 'Sim' : 'Não')
                ->color($ipcExpedienteDisponiveis < $minimoIpcExpediente ? 'danger' : 'success'),
            Stat::make('Risco de déficit no plantão', $ipcPlantaoDisponiveis < $operacional->minimoDisponivel(FuncaoOperacional::IPC_PLANTAO) ? 'Sim' : 'Não')
                ->color($ipcPlantaoDisponiveis < $operacional->minimoDisponivel(FuncaoOperacional::IPC_PLANTAO) ? 'danger' : 'success'),
        ];
    }

    private function periodosAdquiridos(TipoAfastamento $tipo, string $today)
    {
        return AfastamentoPeriodoAquisitivo::query()
            ->where('tipo_afastamento', $tipo->value)
            ->where('dias_disponiveis', '>', 0)
            ->whereDate('data_aquisicao', '<=', $today);
    }
}
