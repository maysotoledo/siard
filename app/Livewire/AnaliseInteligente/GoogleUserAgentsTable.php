<?php

namespace App\Livewire\AnaliseInteligente;

use App\Models\AnaliseRunEvent;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Database\Eloquent\Builder;

class GoogleUserAgentsTable extends TableComponent
{
    public int $runId;

    public function table(Table $table): Table
    {
        $aggregatedQuery = AnaliseRunEvent::query()
            ->where('analise_run_id', $this->runId)
            ->where('event_type', 'access')
            ->whereNotNull('user_agent')
            ->selectRaw('MAX(id) as id, user_agent, COUNT(*) as occurrences, MAX(occurred_at) as occurred_at')
            ->groupBy('user_agent');

        return $table
            ->query(
                (new AnaliseRunEvent)->setTable('user_agent_stats')->newQuery()
                    ->fromSub($aggregatedQuery->toBase(), 'user_agent_stats')
                    ->select('user_agent_stats.*')
            )
            ->columns([
                TextColumn::make('user_agent')
                    ->label('User-Agent')
                    ->wrap(),

                TextColumn::make('occurrences')
                    ->label('Ocorrencias')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('occurred_at')
                    ->label('Ultimo (GMT-3)')
                    ->formatStateUsing(fn ($state): ?string => $state?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s'))
                    ->sortable(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('occurrences')->orderByDesc('id'))
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    public function render()
    {
        return view('livewire.analise-inteligente.google-user-agents-table');
    }

}
