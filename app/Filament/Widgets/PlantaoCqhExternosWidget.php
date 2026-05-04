<?php

namespace App\Filament\Widgets;

use App\Models\PlantaoCqhExterno;
use Filament\Actions;
use Filament\Forms;
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
                Tables\Columns\TextColumn::make('nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('unidade_operacional')->badge(),
                Tables\Columns\TextColumn::make('telefone')->searchable(),
                Tables\Columns\IconColumn::make('apto_cqh')->label('Apto')->boolean(),
                Tables\Columns\IconColumn::make('ativo')->boolean(),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('Adicionar servidor CQH Externo')
                    ->icon('heroicon-o-plus-circle')
                    ->schema($this->formSchema()),
            ])
            ->recordActions([
                Actions\EditAction::make()->schema($this->formSchema()),
                Actions\DeleteAction::make(),
            ])
            ->defaultSort('nome');
    }

    private function query(): Builder
    {
        return PlantaoCqhExterno::query();
    }

    private function formSchema(): array
    {
        return [
            Forms\Components\TextInput::make('nome')->required()->maxLength(255),
            Forms\Components\Select::make('unidade_operacional')
                ->options(['DERF_CONFRESA' => 'DERF Confresa'])
                ->default('DERF_CONFRESA')
                ->required(),
            Forms\Components\TextInput::make('telefone')->tel()->maxLength(50),
            Forms\Components\Toggle::make('apto_cqh')->label('Apto CQH')->default(true),
            Forms\Components\Toggle::make('ativo')->default(true),
            Forms\Components\Textarea::make('observacao')->columnSpanFull(),
        ];
    }
}
