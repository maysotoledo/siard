<?php

namespace App\Filament\Pages;

use App\Jobs\GerarRelatorioIaJob;
use App\Models\AiReport;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Livewire\Attributes\Computed;

class RelatoriosIa extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-cpu-chip';
    protected static ?string $navigationLabel = 'Relatórios IA';
    protected static string|\UnitEnum|null $navigationGroup = 'Investigação IA';
    protected static ?int    $navigationSort  = 20;
    protected static ?string $slug            = 'relatorios-ia';
    protected static ?string $title           = 'Relatórios Gerados por IA';

    protected string $view = 'filament.pages.relatorios-ia';

    public ?int $viewingReportId = null;

    #[Computed]
    public function reports(): \Illuminate\Database\Eloquent\Collection
    {
        return AiReport::query()
            ->with(['investigationContext', 'user'])
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();
    }

    public function verRelatorio(int $id): void
    {
        if (! $this->findUserReport($id)) {
            Notification::make()
                ->title('Relatório não encontrado ou sem permissão de acesso.')
                ->danger()
                ->send();

            return;
        }

        $this->viewingReportId = $id;
        $this->mountAction('verRespostaModal');
    }

    public function regenerarRelatorio(int $id): void
    {
        $report = $this->findUserReport($id);

        if (! $report) return;

        $report->update(['status' => 'pending', 'resposta' => null, 'erro' => null]);
        GerarRelatorioIaJob::dispatch($report->id);

        Notification::make()->title('Relatório reenfileirado para geração.')->success()->send();
    }

    public function excluirRelatorio(int $id): void
    {
        $report = $this->findUserReport($id);

        if (! $report) return;

        $report->delete();

        Notification::make()->title('Relatório excluído.')->success()->send();
    }

    protected function getActions(): array
    {
        return [
            Action::make('verRespostaModal')
                ->label('Ver resposta')
                ->modalHeading(function (): string {
                    if (! $this->viewingReportId) return 'Resposta';
                    $r = $this->findUserReport($this->viewingReportId);
                    return $r ? ucfirst(str_replace('_', ' ', $r->tipo)) : 'Resposta';
                })
                ->modalWidth(Width::SevenExtraLarge)
                ->modalContent(function (): \Illuminate\Contracts\View\View {
                    $report = $this->viewingReportId ? $this->findUserReport($this->viewingReportId) : null;
                    return view('filament.pages.partials.modal-relatorio-ia', ['report' => $report]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
        ];
    }

    private function findUserReport(int $id): ?AiReport
    {
        return AiReport::query()
            ->with(['investigationContext', 'user'])
            ->where('user_id', auth()->id())
            ->whereKey($id)
            ->first();
    }
}
