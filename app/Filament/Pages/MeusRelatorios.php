<?php

namespace App\Filament\Pages;

use App\Models\AnaliseInvestigation;
use App\Models\AnaliseRun;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class MeusRelatorios extends Page implements HasTable
{
    use HasPageShield;
    use Tables\Concerns\InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Minhas Investigações';
    protected static ?string $title = 'Minhas Investigações';
    protected static ?string $slug = 'meus-relatorios';

    protected string $view = 'filament.pages.meus-relatorios';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Análise Telemática';
    }

    public static function getNavigationSort(): ?int
    {
        return 90;
    }

    public function mount(): void
    {
        $this->ensureInvestigationsForOrphanRuns();
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query($this->getTableQuery())
            ->defaultSort('id', 'desc')

            ->toolbarActions([
                BulkAction::make('deleteSelected')
                    ->label('Excluir selecionados')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Excluir investigações selecionadas')
                    ->modalDescription('Essa ação excluirá os relatórios dos alvos vinculados. Deseja continuar?')
                    ->modalSubmitActionLabel('Excluir')
                    ->fetchSelectedRecords(false)
                    ->action(function (Collection $records): void {
                        $ids = $records->values()->all();

                        if (count($ids) === 0) {
                            Notification::make()
                                ->title('Nenhum relatório selecionado.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $investigationIds = AnaliseInvestigation::query()
                            ->whereKey($ids)
                            ->where('user_id', auth()->id())
                            ->pluck('id')
                            ->all();

                        AnaliseRun::whereIn('investigation_id', $investigationIds)->delete();
                        $deleted = AnaliseInvestigation::whereIn('id', $investigationIds)->delete();

                        Notification::make()
                            ->title("{$deleted} investigação(ões) excluída(s) com sucesso.")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])

            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('source_label')
                    ->label('Plataforma')
                    ->state(fn (AnaliseInvestigation $record): string => $this->resolveInvestigationSourceLabel($record->source))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'WhatsApp' => 'success',
                        'Instagram' => 'info',
                        'Genérico' => 'gray',
                        'Google' => 'primary',
                        'Apple' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('name')
                    ->label('Investigação')
                    ->searchable()
                    ->copyable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('runs_avg_progress')
                    ->label('Progresso')
                    ->state(fn (AnaliseInvestigation $record): int => (int) round((float) ($record->runs_avg_progress ?? 0)))
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em (GMT-3)')
                    ->dateTime('d/m/Y H:i:s')
                    ->timezone('America/Sao_Paulo')
                    ->sortable(),

                Tables\Columns\ViewColumn::make('acoes')
                    ->label('Ações')
                    ->view('filament.pages.partials.meus-relatorios-acoes'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('source')
                    ->label('Plataforma')
                    ->options([
                        'whatsapp' => 'WhatsApp',
                        'instagram' => 'Instagram',
                        'google' => 'Google',
                        'apple' => 'Apple',
                        'generico' => 'Genérico',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        return $value ? $query->where('source', $value) : $query;
                    }),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    protected function getTableQuery(): Builder
    {
        return AnaliseInvestigation::query()
            ->with('user')
            ->where('user_id', auth()->id())
            ->select([
                'analise_investigations.id',
                'analise_investigations.user_id',
                'analise_investigations.name',
                'analise_investigations.source',
                'analise_investigations.created_at',
            ])
            ->withAvg('runs', 'progress');
    }

    /**
     * Instagram:
     * - se tiver conta (account_identifier) => @conta
     * - senão, se tiver nome (first_name) => Nome
     * - senão => target/—
     */
    protected function resolveInstagramAlvo(AnaliseRun $run): string
    {
        $report = $run->report;

        if (is_string($report) && trim($report) !== '') {
            $decoded = json_decode($report, true);
            if (is_array($decoded)) {
                $report = $decoded;
            }
        }

        $handle = trim((string) data_get($report, '_parsed.account_identifier'));
        if ($handle !== '' && ! preg_match('/^\d+$/', $handle)) {
            return str_starts_with($handle, '@') ? $handle : "@{$handle}";
        }

        $name = trim((string) data_get($report, '_parsed.first_name'));
        if ($name !== '') {
            return $name;
        }

        $fallback = trim((string) ($run->target ?? ''));
        return $fallback !== '' ? $fallback : '—';
    }

    public function resolveSource(AnaliseRun $run): string
    {
        $source = $run->source_extracted ?? null;

        if (is_string($source) && trim($source) !== '') {
            $source = strtolower(trim($source));

            if ($source === 'generic') {
                return 'generico';
            }

            if (in_array($source, ['whatsapp', 'instagram', 'google', 'apple', 'generico'], true)) {
                return $source;
            }
        }

        return 'whatsapp';
    }

    public function resolveInvestigationSourceLabel(string $source): string
    {
        return match (strtolower(trim($source))) {
            'instagram' => 'Instagram',
            'google' => 'Google',
            'apple' => 'Apple',
            'whatsapp' => 'WhatsApp',
            'generico', 'generic' => 'Genérico',
            default => 'Genérico',
        };
    }

    public function resolveSourceLabel(AnaliseRun $run): string
    {
        return match ($this->resolveSource($run)) {
            'instagram' => 'Instagram',
            'google' => 'Google',
            'apple' => 'Apple',
            'whatsapp' => 'WhatsApp',
            'generico' => 'Genérico',
            default => 'Genérico',
        };
    }

    public function resolveViewUrl(AnaliseInvestigation $investigation): string
    {
        $run = $investigation->runs()->orderBy('id')->first();

        if ($investigation->source === 'google') {
            return AnaliseInteligenteGoogle::getUrl(['investigation' => $investigation->id]);
        }

        if ($investigation->source === 'apple') {
            return AnaliseInteligenteApple::getUrl(['investigation' => $investigation->id]);
        }

        if ($investigation->source === 'generico') {
            return AnaliseInteligenteGenerico::getUrl(['investigation' => $investigation->id]);
        }

        if ($investigation->source === 'instagram') {
            return AnaliseInteligenteInsta::getUrl(['investigation' => $investigation->id]);
        }

        return match ($investigation->source) {
            'instagram' => $run
                ? AnaliseInteligenteInsta::getUrl(['run' => $run->id])
                : AnaliseInteligenteInsta::getUrl(),
            'google' => $run
                ? AnaliseInteligenteGoogle::getUrl(['run' => $run->id])
                : AnaliseInteligenteGoogle::getUrl(),
            'apple' => $run
                ? AnaliseInteligenteApple::getUrl(['run' => $run->id])
                : AnaliseInteligenteApple::getUrl(),
            'generico' => $run
                ? AnaliseInteligenteGenerico::getUrl(['run' => $run->id])
                : AnaliseInteligenteGenerico::getUrl(),
            default => AnaliseInteligenteWPP::getUrl(['investigation' => $investigation->id]),
        };
    }

    protected function ensureInvestigationsForOrphanRuns(): void
    {
        AnaliseRun::query()
            ->where('user_id', auth()->id())
            ->whereNull('investigation_id')
            ->orderBy('id')
            ->get()
            ->each(function (AnaliseRun $run): void {
                $source = $this->resolveSourceFromReport($run);

                $investigation = AnaliseInvestigation::create([
                    'user_id' => $run->user_id,
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'name' => $this->resolveInvestigationName($run, $source),
                    'source' => $source,
                    'created_at' => $run->created_at,
                    'updated_at' => $run->updated_at,
                ]);

                $run->investigation_id = $investigation->id;
                $run->save();
            });
    }

    protected function resolveSourceFromReport(AnaliseRun $run): string
    {
        $source = strtolower(trim((string) data_get($run->report, '_source')));

        return match ($source) {
            'instagram' => 'instagram',
            'google' => 'google',
            'apple' => 'apple',
            'generico', 'generic' => 'generico',
            default => 'whatsapp',
        };
    }

    protected function resolveInvestigationName(AnaliseRun $run, string $source): string
    {
        if ($source === 'instagram') {
            return 'Instagram ' . $this->resolveInstagramAlvo($run);
        }

        $target = trim((string) ($run->target ?: data_get($run->report, '_parsed.target') ?: data_get($run->report, '_parsed.account_identifier')));

        return $target !== ''
            ? ucfirst($source) . ' ' . $target
            : ucfirst($source) . ' #' . $run->id;
    }

    #[On('delete-investigation')]
    public function deleteInvestigation(int $investigationId): void
    {
        $investigation = AnaliseInvestigation::query()
            ->whereKey($investigationId)
            ->where('user_id', auth()->id())
            ->first();

        if (! $investigation) {
            Notification::make()
                ->title('Investigação não encontrada ou você não tem permissão.')
                ->danger()
                ->send();
            return;
        }

        AnaliseRun::where('investigation_id', $investigation->id)->delete();
        $investigation->delete();

        Notification::make()
            ->title('Investigação excluída')
            ->success()
            ->send();
    }
}
