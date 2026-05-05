<?php

namespace App\Filament\Pages;

use App\Models\AiAnalysis;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class MinhasAnalisesIa extends Page implements HasTable
{
    use HasPageShield;
    use Tables\Concerns\InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;
    protected static ?string $navigationLabel = 'Minhas Analises de IA';
    protected static ?string $title = 'Minhas Analises de IA';
    protected static ?string $slug = 'minhas-analises-ia';

    protected string $view = 'filament.pages.minhas-analises-ia';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Investigação Telemática';
    }

    public static function getNavigationSort(): ?int
    {
        return 95;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query($this->getTableQuery())
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $this->resolveTipoLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        'resumo_tecnico' => 'info',
                        'linha_investigacao' => 'warning',
                        'relatorio_policial' => 'success',
                        'analise_noturna' => 'gray',
                        'analise_ips_moveis' => 'primary',
                        'pergunta_livre' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('analiseRun.investigation.name')
                    ->label('Investigacao')
                    ->searchable()
                    ->wrap()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('analiseRun.target')
                    ->label('Alvo')
                    ->searchable()
                    ->wrap()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('status_view')
                    ->label('Status')
                    ->state(fn (AiAnalysis $record): string => $this->resolveStatus($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'queued' => 'gray',
                        'processing' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('progress_view')
                    ->label('Progresso')
                    ->state(fn (AiAnalysis $record): string => $this->resolveProgress($record) . '%'),


                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criada em (GMT-3)')
                    ->dateTime('d/m/Y H:i:s')
                    ->timezone('America/Sao_Paulo')
                    ->sortable(),
            ])
            ->actions([
                Action::make('verResposta')
                    ->label('Ver resposta')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading('Resposta da Analise de IA')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (AiAnalysis $record): HtmlString => new HtmlString(
                        '<div class="space-y-4 text-sm">'
                        . '<div><strong>Status:</strong> ' . e($this->resolveStatus($record)) . ' (' . e((string) $this->resolveProgress($record)) . '%)</div>'
                        . '<div><strong>Pergunta:</strong><br>' . nl2br(e((string) $record->pergunta)) . '</div>'
                        . '<div><strong>Resposta:</strong><br>' . nl2br(e((string) ($record->resposta ?: 'Sem resposta gerada ainda.'))) . '</div>'
                        . ($record->erro ? '<div><strong>Erro:</strong><br>' . nl2br(e((string) $record->erro)) . '</div>' : '')
                        . '</div>'
                    )),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    protected function getTableQuery(): Builder
    {
        return AiAnalysis::query()
            ->with(['analiseRun.investigation'])
            ->where('user_id', auth()->id());
    }

    private function resolveTipoLabel(?string $tipo): string
    {
        return match ($tipo) {
            'resumo_tecnico' => 'Resumo tecnico',
            'linha_investigacao' => 'Linha de investigacao',
            'relatorio_policial' => 'Minuta de relatorio',
            'analise_noturna' => 'Acessos noturnos',
            'analise_ips_moveis' => 'IPs moveis',
            'pergunta_livre' => 'Pergunta livre',
            default => (string) ($tipo ?: '-'),
        };
    }

    private function resolveStatus(AiAnalysis $record): string
    {
        if (AiAnalysis::hasStatusColumn()) {
            return (string) ($record->status ?: 'processing');
        }

        return $record->resposta ? 'completed' : 'processing';
    }

    private function resolveProgress(AiAnalysis $record): int
    {
        if (AiAnalysis::hasProgressColumn()) {
            return max(0, min(100, (int) ($record->progress ?? 0)));
        }

        return $record->resposta ? 100 : 50;
    }
}
