<?php

namespace App\Livewire\AnaliseInteligente;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Pagination\LengthAwarePaginator;

class BurstHoursTable extends TableComponent
{
    public array $rows = [];

    public function table(Table $table): Table
    {
        $maxCount = collect($this->rows)->max('count') ?: 1;

        return $table
            ->records(fn (?string $search): LengthAwarePaginatorContract => $this->buildPaginator($search))
            ->columns([
                TextColumn::make('label')
                    ->label('Hora (GMT-3)')
                    ->fontFamily('mono')
                    ->formatStateUsing(fn (string $state): string => $state . 'h')
                    ->action(fn (array $record) => $this->openBurst($record['burst_hour'])),

                TextColumn::make('count')
                    ->label('Conexões')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 20 => 'danger',
                        $state >= 10 => 'warning',
                        default      => 'gray',
                    })
                    ->action(fn (array $record) => $this->openBurst($record['burst_hour'])),

                TextColumn::make('intensity')
                    ->label('Intensidade')
                    ->state(fn (array $record): int => (int) $record['count'])
                    ->formatStateUsing(function (int $state) use ($maxCount): string {
                        $pct   = round(($state / $maxCount) * 100);
                        $color = $state >= 20 ? '#ef4444' : ($state >= 10 ? '#facc15' : '#60a5fa');
                        return <<<HTML
                            <div style="display:flex;align-items:center;gap:8px;min-width:140px;">
                                <div style="flex:1;height:6px;border-radius:9999px;background:#e5e7eb;overflow:hidden;">
                                    <div style="width:{$pct}%;height:100%;border-radius:9999px;background:{$color};"></div>
                                </div>
                                <span style="font-size:11px;color:#9ca3af;width:28px;text-align:right;">{$pct}%</span>
                            </div>
                        HTML;
                    })
                    ->html()
                    ->action(fn (array $record) => $this->openBurst($record['burst_hour'])),
            ])
            ->defaultSort('count', 'desc')
            ->paginated([24, 48, 96])
            ->defaultPaginationPageOption(24);
    }

    public function openBurst(string $burstHour): void
    {
        $this->dispatch('open-burst-modal', burstHour: $burstHour);
    }

    private function buildPaginator(?string $search = null): LengthAwarePaginator
    {
        $filtered = $this->rows;

        $search = trim((string) $search);
        if ($search !== '') {
            $needle   = mb_strtolower($search);
            $filtered = array_values(array_filter(
                $filtered,
                fn ($r) => str_contains(mb_strtolower((string) data_get($r, 'label', '')), $needle)
            ));
        }

        usort($filtered, fn ($a, $b) => (int) data_get($b, 'count', 0) <=> (int) data_get($a, 'count', 0));

        $perPage = (int) ($this->getTableRecordsPerPage() ?: 24);
        $page    = (int) ($this->getTablePage() ?: 1);
        $total   = count($filtered);
        $items   = array_slice($filtered, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path'     => request()->url(),
            'pageName' => $this->getTablePaginationPageName(),
        ]);
    }
}
