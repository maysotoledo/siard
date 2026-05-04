<?php

namespace App\Filament\Widgets;

use App\Enums\StatusAfastamento;
use App\Models\AfastamentoSolicitacao;
use App\Services\Afastamentos\AfastamentoConflictService;
use App\Services\Afastamentos\AfastamentoOperacionalService;
use App\Services\Afastamentos\AfastamentoPrioridadeService;
use App\Services\Afastamentos\AfastamentoService;
use App\Services\Afastamentos\AfastamentoSuggestionService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Validation\ValidationException;

class AfastamentosApprovalWidget extends TableWidget
{
    protected static ?string $heading = 'Solicitações pendentes de aprovação';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AfastamentoSolicitacao::query()
                    ->with(['user', 'periodoAquisitivo'])
                    ->whereIn('status', [StatusAfastamento::SOLICITADO->value, StatusAfastamento::EM_ANALISE->value])
                    ->orderBy('data_inicio')
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Servidor')->searchable(),
                Tables\Columns\TextColumn::make('user.data_ingresso')->label('Ingresso carreira')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('user.data_ingresso_unidade')->label('Ingresso unidade')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('tipo_afastamento')->label('Tipo')->formatStateUsing(fn ($state) => $state?->label())->badge(),
                Tables\Columns\TextColumn::make('data_inicio')->label('Início')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('data_fim')->label('Fim')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('dias_solicitados')->label('Dias'),
                Tables\Columns\TextColumn::make('status')->label('Status')->formatStateUsing(fn ($state) => $state?->label())->badge()->color(fn ($state) => $state?->color() ?? 'gray'),
                Tables\Columns\TextColumn::make('nivel_impacto')->label('Impacto')->formatStateUsing(fn ($state) => $state?->label() ?? '-')->badge()->color(fn ($state) => $state?->color() ?? 'gray'),
                Tables\Columns\TextColumn::make('impacto_score')->label('Score'),
                Tables\Columns\TextColumn::make('prioridade_score')->label('Prioridade')->sortable(),
                Tables\Columns\TextColumn::make('prioridade_posicao')->label('Ranking')->sortable(),
            ])
            ->recordActions([
                Actions\Action::make('aprovar')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->schema([
                        Forms\Components\Textarea::make('justificativa')->label('Justificativa da chefia')->required(),
                    ])
                    ->action(fn (array $data, AfastamentoSolicitacao $record) => $this->executar(
                        fn () => app(AfastamentoService::class)->aprovar($record, $data['justificativa'], $this->isSuperAdmin()),
                        'Afastamento aprovado',
                    )),
                Actions\Action::make('indeferir')
                    ->label('Indeferir')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->schema([
                        Forms\Components\Textarea::make('justificativa')->label('Justificativa da chefia')->required(),
                    ])
                    ->action(fn (array $data, AfastamentoSolicitacao $record) => $this->executar(
                        fn () => app(AfastamentoService::class)->indeferir($record, $data['justificativa']),
                        'Afastamento indeferido',
                    )),
                Actions\Action::make('recalcular')
                    ->label('Recalcular')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn (AfastamentoSolicitacao $record) => $this->executar(
                        fn () => app(AfastamentoService::class)->recalcularImpacto($record),
                        'Impacto recalculado',
                    )),
                Actions\Action::make('analise')
                    ->label('Análise')
                    ->icon('heroicon-o-sparkles')
                    ->modalHeading('Análise inteligente')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (AfastamentoSolicitacao $record) => view('filament.pages.partials.afastamento-analise', [
                        'record' => $record->loadMissing('user'),
                        'conflitos' => app(AfastamentoConflictService::class)->detectar($record),
                        'analisePrioridade' => app(AfastamentoPrioridadeService::class)->analisarConflitosPorPrioridade($record),
                        'sugestoes' => app(AfastamentoSuggestionService::class)->sugerir($record),
                        'coberturas' => app(AfastamentoOperacionalService::class)->servidoresDisponiveisParaCobertura($record),
                    ])),
            ])
            ->headerActions([
                Actions\Action::make('recalcular_todos_impactos')
                    ->label('Calcular/recalcular impactos')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalDescription('Recalcula o impacto de todas as solicitações pendentes de aprovação.')
                    ->action(function (): void {
                        $total = 0;

                        AfastamentoSolicitacao::query()
                            ->with(['user', 'coberturasPlantao'])
                            ->whereIn('status', [StatusAfastamento::SOLICITADO->value, StatusAfastamento::EM_ANALISE->value])
                            ->orderBy('id')
                            ->chunkById(50, function ($solicitacoes) use (&$total): void {
                                foreach ($solicitacoes as $solicitacao) {
                                    app(AfastamentoService::class)->recalcularImpacto($solicitacao);
                                    app(AfastamentoPrioridadeService::class)->atualizarSolicitacao($solicitacao);
                                    $total++;
                                }
                            });

                        Notification::make()
                            ->title('Impactos recalculados')
                            ->body("Solicitações recalculadas: {$total}.")
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nenhuma solicitação pendente');
    }

    private function executar(callable $callback, string $ok): void
    {
        try {
            $callback();
            Notification::make()->title($ok)->success()->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Ação não permitida')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();

            throw $exception;
        }
    }

    private function isSuperAdmin(): bool
    {
        return (bool) auth()->user()?->hasRole('super_admin');
    }
}
