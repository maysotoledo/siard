<?php

namespace App\Filament\Resources\InvestigationContexts\Pages;

use App\Filament\Resources\InvestigationContexts\InvestigationContextResource;
use App\Jobs\GerarRelatorioIaJob;
use App\Models\AiReport;
use App\Services\Investigation\BoContextExtractorService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditInvestigationContext extends EditRecord
{
    protected static string $resource = InvestigationContextResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ── Extrair contexto da investigação ────────────────────────────
            Action::make('extrair_bo')
                ->label('Extrair contexto da investigação')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('Isso vai tentar extrair o texto do arquivo anexo e sobrescrever o campo "Texto do BO".')
                ->action(function () {
                    $record = $this->getRecord();

                    if (! $record->arquivo_path) {
                        Notification::make()->title('Nenhum arquivo anexado.')->warning()->send();
                        return;
                    }

                    $text = app(BoContextExtractorService::class)->extract($record);

                    if ($text !== '') {
                        $record->update(['texto_extraido' => $text]);
                        Notification::make()->title('Texto extraído com sucesso.')->success()->send();
                    } else {
                        Notification::make()
                            ->title('Extração automática não encontrou texto.')
                            ->body('Preencha manualmente o campo "Texto do BO".')
                            ->warning()
                            ->send();
                    }
                }),

            // ── Gerar Relatório Completo ──────────────────────────
            Action::make('gerar_relatorio_completo')
                ->label('Relatório Completo IA')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->action(fn () => $this->despacharRelatorio('relatorio_completo', 'Relatório completo enfileirado.')),

            // ── Gerar Resumo Técnico ──────────────────────────────
            Action::make('gerar_resumo')
                ->label('Resumo Técnico IA')
                ->icon('heroicon-o-document-chart-bar')
                ->color('gray')
                ->action(fn () => $this->despacharRelatorio('resumo_tecnico', 'Resumo técnico enfileirado.')),

            // ── Gerar Linha de Investigação ───────────────────────
            Action::make('gerar_linha')
                ->label('Linha de Investigação IA')
                ->icon('heroicon-o-magnifying-glass')
                ->color('gray')
                ->action(fn () => $this->despacharRelatorio('linha_investigacao', 'Linha de investigação enfileirada.')),

            // ── Gerar Conclusão ───────────────────────────────────
            Action::make('gerar_conclusao')
                ->label('Conclusão IA')
                ->icon('heroicon-o-check-badge')
                ->color('gray')
                ->action(fn () => $this->despacharRelatorio('conclusao', 'Conclusão enfileirada.')),

            // ── Gerar Minuta para Autoridade ─────────────────────
            Action::make('gerar_minuta')
                ->label('Minuta para Autoridade IA')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('warning')
                ->action(fn () => $this->despacharRelatorio('minuta_autoridade', 'Minuta para autoridade enfileirada.')),

            DeleteAction::make(),
        ];
    }

    private function despacharRelatorio(string $tipo, string $mensagem): void
    {
        $record = $this->getRecord();

        if (! $record->hasTextoExtraido()) {
            Notification::make()
                ->title('Anexe ou preencha o contexto da investigação antes de gerar o relatório por IA.')
                ->danger()
                ->send();
            return;
        }

        $aiReport = AiReport::create([
            'investigation_context_id' => $record->id,
            'analise_run_id'           => $record->analise_run_id,
            'user_id'                  => auth()->id(),
            'tipo'                     => $tipo,
            'status'                   => 'pending',
            'prompt'                   => '', // preenchido pelo job
        ]);

        GerarRelatorioIaJob::dispatch($aiReport->id);

        Notification::make()
            ->title($mensagem)
            ->body('Veja os resultados em "Relatórios IA".')
            ->success()
            ->send();
    }
}
