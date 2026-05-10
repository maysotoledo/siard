<?php

namespace App\Filament\Resources\AiAnalyses;

use App\Filament\Resources\AiAnalyses\Pages;
use App\Models\AiAnalysis;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class AiAnalysisResource extends Resource
{
    protected static ?string $model = AiAnalysis::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|UnitEnum|null $navigationGroup = 'Administração do Sistema';

    protected static ?string $navigationLabel = 'Análises IA Processadas';

    protected static ?string $modelLabel = 'Análise IA';

    protected static ?string $pluralModelLabel = 'Análises IA';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('analise_run_id')
                    ->label('Relatório')
                    ->relationship('analiseRun', 'target')
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('tipo')
                    ->label('Tipo')
                    ->required(),

                Forms\Components\TextInput::make('modelo')
                    ->label('Modelo'),

                Forms\Components\Textarea::make('pergunta')
                    ->label('Pergunta')
                    ->rows(5)
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('resposta')
                    ->label('Resposta')
                    ->rows(15)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Criado por')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Não informado'),

                Tables\Columns\TextColumn::make('analiseRun.target')
                    ->label('Alvo')
                    ->searchable()
                    ->placeholder('Sem alvo'),

                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('modelo')
                    ->label('Modelo')
                    ->badge()
                    ->placeholder('Não informado'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em (GMT-3)')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Sao_Paulo')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Actions\ViewAction::make()
                    ->label('Ver'),

                Actions\DeleteAction::make()
                    ->label('Excluir'),
            ])
            ->toolbarActions([
                Actions\DeleteBulkAction::make()
                    ->label('Excluir selecionados'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiAnalyses::route('/'),
            'view' => Pages\ViewAiAnalysis::route('/{record}'),
        ];
    }
}
