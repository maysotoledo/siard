<?php

namespace App\Filament\Widgets;

use App\Enums\TipoAfastamento;
use App\Services\Afastamentos\AfastamentoPrioridadeService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

class AfastamentosPriorityWidget extends TableWidget
{
    protected static ?string $heading = 'Top 10 prioridade para férias';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $ranking = collect(app(AfastamentoPrioridadeService::class)->calcularRanking(TipoAfastamento::FERIAS))
            ->take(10)
            ->map(fn (array $item): array => [
                'posicao' => $item['posicao'],
                'name' => $item['user']->name,
                'score' => $item['score'],
                'nivel' => $item['nivel']->label(),
                'motivo' => $item['motivo'],
            ]);

        return $table
            ->records(fn (): Collection => $ranking)
            ->columns([
                Tables\Columns\TextColumn::make('posicao')->label('#'),
                Tables\Columns\TextColumn::make('name')->label('Servidor'),
                Tables\Columns\TextColumn::make('score')->label('Score'),
                Tables\Columns\TextColumn::make('nivel')->label('Nível')->badge(),
                Tables\Columns\TextColumn::make('motivo')->label('Motivo')->limit(80),
            ]);
    }
}
