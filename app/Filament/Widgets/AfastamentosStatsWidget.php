<?php

namespace App\Filament\Widgets;

use App\Enums\FuncaoOperacional;
use App\Enums\StatusAfastamento;
use App\Enums\TipoAfastamento;
use App\Models\AfastamentoCoberturaPlantao;
use App\Models\AfastamentoPeriodoAquisitivo;
use App\Models\AfastamentoSolicitacao;
use App\Models\User;
use App\Services\Afastamentos\AfastamentoOperacionalService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class AfastamentosStatsWidget extends StatsOverviewWidget implements HasActions
{
    use InteractsWithActions;

    protected string $view = 'filament.widgets.afastamentos-stats-widget';

    #[On('afastamentosUpdated')]
    #[On('plantaoUpdated')]
    public function refreshWidget(): void
    {
    }

    protected function getStats(): array
    {
        $today = now()->toDateString();
        $ativos = $this->ativosBase($today);
        $aprovadosAteFimDoAno = $this->aprovadosAteFimDoAnoBase($today);
        $operacional = app(AfastamentoOperacionalService::class);

        $ipcExpedienteDisponiveis = $operacional->disponiveisDaFuncao(FuncaoOperacional::IPC_EXPEDIENTE, $today, $today);
        $ipcPlantaoDisponiveis = $operacional->disponiveisDaFuncao(FuncaoOperacional::IPC_PLANTAO, $today, $today);
        $epcExpedienteDisponiveis = $operacional->disponiveisDaFuncao(FuncaoOperacional::EPC_EXPEDIENTE, $today, $today);
        $epcPlantaoDisponiveis = $operacional->disponiveisDaFuncao(FuncaoOperacional::EPC_PLANTAO, $today, $today);
        $minimoIpcExpediente = $operacional->minimoDisponivel(FuncaoOperacional::IPC_EXPEDIENTE);
        $minimoIpcPlantao = $operacional->minimoDisponivel(FuncaoOperacional::IPC_PLANTAO);
        $minimoEpcExpediente = $operacional->minimoDisponivel(FuncaoOperacional::EPC_EXPEDIENTE);
        $minimoEpcPlantao = $operacional->minimoDisponivel(FuncaoOperacional::EPC_PLANTAO);

        return [
            $this->statClicavel('Afastamentos aprovados', (clone $aprovadosAteFimDoAno)->count(), 'ativos_hoje'),

            $this->statClicavel(
                'Férias ativas hoje',
                (clone $ativos)->where('tipo_afastamento', TipoAfastamento::FERIAS->value)->count(),
                'ferias_hoje',
            ),

            $this->statClicavel(
                'Licenças-prêmio ativas hoje',
                (clone $ativos)->where('tipo_afastamento', TipoAfastamento::LICENCA_PREMIO->value)->count(),
                'licenca_premio_hoje',
            ),

            $this->statClicavel(
                'Solicitações pendentes',
                AfastamentoSolicitacao::query()->pendentes()->count(),
                'pendentes',
            ),

            $this->statClicavel(
                'Férias vencidas/adquiridas',
                $this->periodosAdquiridos(TipoAfastamento::FERIAS, $today)->count(),
                'ferias_adquiridas',
            ),

            $this->statClicavel(
                'Licença-prêmio adquirida',
                $this->periodosAdquiridos(TipoAfastamento::LICENCA_PREMIO, $today)->count(),
                'licenca_premio_adquirida',
            ),

            $this->statClicavel('IPC expediente disponíveis', $ipcExpedienteDisponiveis, 'disponiveis_ipc_expediente')
                ->color($ipcExpedienteDisponiveis < $minimoIpcExpediente ? 'danger' : 'success'),

            $this->statClicavel('IPC plantão disponíveis', $ipcPlantaoDisponiveis, 'disponiveis_ipc_plantao')
                ->color($ipcPlantaoDisponiveis < $minimoIpcPlantao ? 'danger' : 'success'),

            $this->statClicavel('EPC expediente disponíveis', $epcExpedienteDisponiveis, 'disponiveis_epc_expediente')
                ->color($epcExpedienteDisponiveis < $minimoEpcExpediente ? 'danger' : 'success'),

            $this->statClicavel('EPC plantão disponíveis', $epcPlantaoDisponiveis, 'disponiveis_epc_plantao')
                ->color($epcPlantaoDisponiveis < $minimoEpcPlantao ? 'danger' : 'success'),

            $this->statClicavel(
                'IPC expediente deslocados para plantão',
                AfastamentoCoberturaPlantao::query()
                    ->where('status', 'aprovada')
                    ->whereDate('data_inicio', '<=', $today)
                    ->whereDate('data_fim', '>=', $today)
                    ->count(),
                'deslocados',
            ),

            $this->statClicavel(
                'Afastamentos do expediente',
                (clone $ativos)
                    ->whereHas('user.roles', fn ($query) => $query->whereIn('name', ['ipc', 'epc', 'cartorio_central', 'dpc']))
                    ->count(),
                'afastamentos_expediente',
            ),

            $this->statClicavel(
                'Afastamentos do plantão',
                (clone $ativos)
                    ->whereHas('user.roles', fn ($query) => $query->whereIn('name', ['ipc_plantao', 'epc_plantao']))
                    ->count(),
                'afastamentos_plantao',
            ),

            // Cards de risco mantêm-se NÃO clicáveis (não há lista de servidores associada).
            Stat::make('Risco de déficit no expediente', $ipcExpedienteDisponiveis < $minimoIpcExpediente ? 'Sim' : 'Não')
                ->color($ipcExpedienteDisponiveis < $minimoIpcExpediente ? 'danger' : 'success'),

            Stat::make('Risco de déficit no plantão', $ipcPlantaoDisponiveis < $minimoIpcPlantao ? 'Sim' : 'Não')
                ->color($ipcPlantaoDisponiveis < $minimoIpcPlantao ? 'danger' : 'success'),
        ];
    }

    /**
     * Cria um Stat clicável que dispara a action genérica de visualização de servidores.
     */
    private function statClicavel(string $label, int|string $value, string $tipo): Stat
    {
        return Stat::make($label, $value)
            ->extraAttributes([
                'wire:click' => "mountAction('verServidores', { tipo: '{$tipo}' })",
                'data-stat-clickable' => 'true',
                'title' => 'Clique para ver os servidores',
                'role' => 'button',
                'tabindex' => '0',
            ]);
    }

    public function verServidoresAction(): Action
    {
        return Action::make('verServidores')
            ->modalHeading(function (array $arguments): string {
                return $this->dadosParaModal($arguments['tipo'] ?? '')['titulo'];
            })
            ->modalDescription(function (array $arguments): ?string {
                return $this->dadosParaModal($arguments['tipo'] ?? '')['descricao'];
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(function (array $arguments) {
                $dados = $this->dadosParaModal($arguments['tipo'] ?? '');

                return view('filament.widgets.partials.afastamento-servidores-modal', [
                    'titulo' => $dados['titulo'],
                    'descricao' => $dados['descricao'],
                    'linhas' => $dados['linhas'],
                ]);
            });
    }

    /**
     * @return array{titulo: string, descricao: ?string, linhas: Collection<int, array<string, mixed>>}
     */
    private function dadosParaModal(string $tipo): array
    {
        $today = now()->toDateString();
        $hojeFormatado = now()->format('d/m/Y');
        $operacional = app(AfastamentoOperacionalService::class);

        return match ($tipo) {
            'ativos_hoje' => [
                'titulo' => 'Afastamentos aprovados',
                'descricao' => 'Servidores com afastamento aprovado previsto até 31/12/'.now()->year.'.',
                'linhas' => $this->linhasDeAfastamentos($this->aprovadosAteFimDoAnoBase($today)),
            ],
            'ferias_hoje' => [
                'titulo' => 'Férias ativas hoje',
                'descricao' => "Servidores em férias vigentes em {$hojeFormatado}.",
                'linhas' => $this->linhasDeAfastamentos($this->ativosBase($today)
                    ->where('tipo_afastamento', TipoAfastamento::FERIAS->value)),
            ],
            'licenca_premio_hoje' => [
                'titulo' => 'Licenças-prêmio ativas hoje',
                'descricao' => "Servidores em licença-prêmio vigente em {$hojeFormatado}.",
                'linhas' => $this->linhasDeAfastamentos($this->ativosBase($today)
                    ->where('tipo_afastamento', TipoAfastamento::LICENCA_PREMIO->value)),
            ],
            'pendentes' => [
                'titulo' => 'Solicitações pendentes',
                'descricao' => 'Solicitações aguardando análise/aprovação.',
                'linhas' => $this->linhasDeAfastamentos(
                    AfastamentoSolicitacao::query()->with('user')->pendentes()->orderBy('data_inicio'),
                ),
            ],
            'ferias_adquiridas' => [
                'titulo' => 'Férias vencidas/adquiridas',
                'descricao' => 'Servidores com período aquisitivo de férias adquirido e dias disponíveis.',
                'linhas' => $this->linhasDePeriodos(
                    $this->periodosAdquiridos(TipoAfastamento::FERIAS, $today),
                ),
            ],
            'licenca_premio_adquirida' => [
                'titulo' => 'Licença-prêmio adquirida',
                'descricao' => 'Servidores com licença-prêmio adquirida e dias disponíveis.',
                'linhas' => $this->linhasDePeriodos(
                    $this->periodosAdquiridos(TipoAfastamento::LICENCA_PREMIO, $today),
                ),
            ],
            'disponiveis_ipc_expediente',
            'disponiveis_ipc_plantao',
            'disponiveis_epc_expediente',
            'disponiveis_epc_plantao' => $this->dadosDisponiveis($tipo, $today, $hojeFormatado, $operacional),
            'deslocados' => [
                'titulo' => 'IPC expediente deslocados para plantão',
                'descricao' => "Coberturas aprovadas vigentes em {$hojeFormatado}.",
                'linhas' => $this->linhasDeCoberturas($today),
            ],
            'afastamentos_expediente' => [
                'titulo' => 'Afastamentos do expediente',
                'descricao' => "Servidores do expediente (IPC, EPC, Cartório central, DPC) afastados em {$hojeFormatado}.",
                'linhas' => $this->linhasDeAfastamentos($this->ativosBase($today)
                    ->whereHas('user.roles', fn ($query) => $query->whereIn('name', ['ipc', 'epc', 'cartorio_central', 'dpc']))),
            ],
            'afastamentos_plantao' => [
                'titulo' => 'Afastamentos do plantão',
                'descricao' => "Servidores do plantão (IPC plantão, EPC plantão) afastados em {$hojeFormatado}.",
                'linhas' => $this->linhasDeAfastamentos($this->ativosBase($today)
                    ->whereHas('user.roles', fn ($query) => $query->whereIn('name', ['ipc_plantao', 'epc_plantao']))),
            ],
            default => [
                'titulo' => 'Servidores',
                'descricao' => null,
                'linhas' => collect(),
            ],
        };
    }

    /**
     * @return array{titulo: string, descricao: ?string, linhas: Collection<int, array<string, mixed>>}
     */
    private function dadosDisponiveis(string $tipo, string $today, string $hojeFormatado, AfastamentoOperacionalService $operacional): array
    {
        $funcao = match ($tipo) {
            'disponiveis_ipc_expediente' => FuncaoOperacional::IPC_EXPEDIENTE,
            'disponiveis_ipc_plantao' => FuncaoOperacional::IPC_PLANTAO,
            'disponiveis_epc_expediente' => FuncaoOperacional::EPC_EXPEDIENTE,
            'disponiveis_epc_plantao' => FuncaoOperacional::EPC_PLANTAO,
        };

        $linhas = $operacional->disponiveisDaFuncaoLista($funcao, $today, $today)
            ->map(fn (User $user): array => [
                'nome' => $user->name,
                'sub' => $user->email,
                'meta' => null,
                'badge' => 'Disponível',
                'badgeColor' => 'success',
            ]);

        return [
            'titulo' => 'Servidores disponíveis - '.$funcao->label(),
            'descricao' => "Servidores ativos da função {$funcao->label()} que não estão afastados nem em cobertura em {$hojeFormatado}.",
            'linhas' => $linhas,
        ];
    }

    private function ativosBase(string $today): \Illuminate\Database\Eloquent\Builder
    {
        return AfastamentoSolicitacao::query()
            ->with('user')
            ->where('status', StatusAfastamento::APROVADO->value)
            ->whereDate('data_inicio', '<=', $today)
            ->whereDate('data_fim', '>=', $today);
    }

    private function aprovadosAteFimDoAnoBase(string $today): \Illuminate\Database\Eloquent\Builder
    {
        return AfastamentoSolicitacao::query()
            ->with('user')
            ->where('status', StatusAfastamento::APROVADO->value)
            ->whereDate('data_inicio', '>=', $today)
            ->whereDate('data_inicio', '<=', now()->endOfYear()->toDateString());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function linhasDeAfastamentos(\Illuminate\Database\Eloquent\Builder $query): Collection
    {
        return $query->orderBy('data_inicio')
            ->get()
            ->map(fn (AfastamentoSolicitacao $s): array => [
                'nome' => $s->user?->name ?? '-',
                'sub' => $s->user?->email,
                'meta' => sprintf(
                    '%s • %s a %s',
                    $s->tipo_afastamento?->label() ?? '-',
                    optional($s->data_inicio)->format('d/m/Y') ?? '-',
                    optional($s->data_fim)->format('d/m/Y') ?? '-',
                ),
                'badge' => $s->status?->label(),
                'badgeColor' => $s->status?->color() ?? 'gray',
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function linhasDePeriodos(\Illuminate\Database\Eloquent\Builder $query): Collection
    {
        return $query->with('user')
            ->orderBy('data_aquisicao')
            ->get()
            ->map(fn (AfastamentoPeriodoAquisitivo $p): array => [
                'nome' => $p->user?->name ?? '-',
                'sub' => $p->user?->email,
                'meta' => sprintf(
                    'Adquirido em %s • %d dias disponíveis',
                    optional($p->data_aquisicao)->format('d/m/Y') ?? '-',
                    (int) $p->dias_disponiveis,
                ),
                'badge' => null,
                'badgeColor' => null,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function linhasDeCoberturas(string $today): Collection
    {
        return AfastamentoCoberturaPlantao::query()
            ->with(['servidorCobertura', 'servidorPlantaoAfastado'])
            ->where('status', 'aprovada')
            ->whereDate('data_inicio', '<=', $today)
            ->whereDate('data_fim', '>=', $today)
            ->orderBy('data_inicio')
            ->get()
            ->map(fn (AfastamentoCoberturaPlantao $c): array => [
                'nome' => $c->servidorCobertura?->name ?? '-',
                'sub' => $c->servidorCobertura?->email,
                'meta' => sprintf(
                    'Cobre %s • %s a %s',
                    $c->servidorPlantaoAfastado?->name ?? '-',
                    optional($c->data_inicio)->format('d/m/Y') ?? '-',
                    optional($c->data_fim)->format('d/m/Y') ?? '-',
                ),
                'badge' => 'Cobertura',
                'badgeColor' => 'warning',
            ])
            ->values();
    }

    private function periodosAdquiridos(TipoAfastamento $tipo, string $today): \Illuminate\Database\Eloquent\Builder
    {
        return AfastamentoPeriodoAquisitivo::query()
            ->where('tipo_afastamento', $tipo->value)
            ->where('dias_disponiveis', '>', 0)
            ->whereDate('data_aquisicao', '<=', $today);
    }
}
