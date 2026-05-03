<?php

namespace App\Filament\Widgets;

use App\Enums\StatusAfastamento;
use App\Models\AfastamentoSolicitacao;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AfastamentosUpcomingWidget extends TableWidget
{
    protected static ?string $heading = 'Próximos afastamentos e risco operacional';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AfastamentoSolicitacao::query()
                    ->with('user')
                    ->whereIn('status', [StatusAfastamento::APROVADO->value, StatusAfastamento::EM_ANALISE->value])
                    ->whereDate('data_inicio', '>=', now()->toDateString())
                    ->orderBy('data_inicio')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Servidor'),
                Tables\Columns\TextColumn::make('tipo_afastamento')->label('Tipo')->formatStateUsing(fn ($state) => $state?->label())->badge(),
                Tables\Columns\TextColumn::make('data_inicio')->label('Início')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('data_fim')->label('Fim')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('nivel_impacto')->label('Impacto')->formatStateUsing(fn ($state) => $state?->label() ?? '-')->badge()->color(fn ($state) => $state?->color() ?? 'gray'),
            ]);
    }
}
