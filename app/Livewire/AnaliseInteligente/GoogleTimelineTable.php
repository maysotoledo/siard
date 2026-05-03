<?php

namespace App\Livewire\AnaliseInteligente;

use App\Models\AnaliseRunEvent;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;

class GoogleTimelineTable extends TableComponent
{
    public int $runId;
    public ?string $scope = null;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
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
                    ->label('Operadora/ISP')
                    ->wrap(),

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
                    ->toggleable(),

                TextColumn::make('logical_port')
                    ->label('Porta Logica')
                    ->toggleable(),

                TextColumn::make('action')
                    ->label('Acao')
                    ->searchable()
                    ->toggleable(),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public function render()
    {
        return view('livewire.analise-inteligente.google-timeline-table');
    }

    private function query()
    {
        $query = AnaliseRunEvent::query()
            ->with('ipEnrichment')
            ->where('analise_run_id', $this->runId)
            ->where('event_type', 'access');

        if ($this->scope === 'night') {
            $query->where(function ($builder): void {
                $builder
                    ->whereRaw('HOUR(CONVERT_TZ(occurred_at, "+00:00", "-03:00")) >= 23')
                    ->orWhereRaw('HOUR(CONVERT_TZ(occurred_at, "+00:00", "-03:00")) <= 6');
            });
        }

        if ($this->scope === 'mobile') {
            $query->whereHas('ipEnrichment', fn ($builder) => $builder->where('mobile', true));
        }

        return $query;
    }
}
