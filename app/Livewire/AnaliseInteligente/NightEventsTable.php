<?php

namespace App\Livewire\AnaliseInteligente;

use App\Filament\Exports\AnaliseRunEventExporter;
use App\Models\AnaliseRunEvent;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;

class NightEventsTable extends TableComponent
{
    public int $runId;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                (new AnaliseRunEvent)->setTable('analise_run_events')->newQuery()
                    ->with('ipEnrichment')
                    ->where('analise_run_id', $this->runId)
                    ->where('event_type', 'access')
                    ->where(function ($q): void {
                        $q->whereRaw("HOUR(CONVERT_TZ(occurred_at, '+00:00', '-03:00')) >= 23")
                          ->orWhereRaw("HOUR(CONVERT_TZ(occurred_at, '+00:00', '-03:00')) <= 6");
                    })
            )
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Data/Hora (GMT-3)')
                    ->formatStateUsing(fn ($state): ?string => $state?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s'))
                    ->sortable(),

                TextColumn::make('ip')
                    ->label('IP')
                    ->fontFamily('mono')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('provider_label')
                    ->label('Provedor')
                    ->wrap()
                    ->searchable(query: fn ($query, $search) => $query->whereHas(
                        'ipEnrichment',
                        fn ($q) => $q->where('isp', 'like', "%{$search}%")->orWhere('org', 'like', "%{$search}%")
                    )),

                TextColumn::make('city_label')
                    ->label('Cidade')
                    ->toggleable(),

                TextColumn::make('connection_type')
                    ->label('Tipo')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('period_flags')
                    ->label('Periodo')
                    ->badge()
                    ->default('Noturno')
                    ->toggleable(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Exportar CSV')
                    ->exporter(AnaliseRunEventExporter::class),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50);
    }

    public function render()
    {
        return view('livewire.analise-inteligente.generic-timeline-table');
    }
}
