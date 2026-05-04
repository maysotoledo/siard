<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PlantaoCqhExternos\PlantaoCqhExternoResource;
use App\Models\PlantaoCqhExterno;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PlantaoCqhExternosWidget extends TableWidget
{
    protected static ?string $heading = 'Servidores CQH Externos';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
            ->columns([
                Tables\Columns\TextColumn::make('ordem')->sortable(),
                Tables\Columns\TextColumn::make('nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('unidade_operacional')->badge(),
                Tables\Columns\TextColumn::make('telefone')->searchable(),
                Tables\Columns\IconColumn::make('apto_cqh')->label('Apto')->boolean(),
                Tables\Columns\IconColumn::make('ativo')->boolean(),
            ])
            ->headerActions([
                Actions\Action::make('gerenciarExternos')
                    ->label('Gerenciar externos')
                    ->icon('heroicon-o-identification')
                    ->url(PlantaoCqhExternoResource::getUrl()),
            ])
            ->defaultSort('ordem');
    }

    private function query(): Builder
    {
        return PlantaoCqhExterno::query();
    }
}
