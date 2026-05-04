<?php

namespace App\Filament\Resources\PlantaoCqhExternos;

use App\Filament\Resources\PlantaoCqhExternos\Pages;
use App\Models\PlantaoCqhExterno;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class PlantaoCqhExternoResource extends Resource
{
    protected static ?string $model = PlantaoCqhExterno::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationLabel = 'Servidores CQH Externos';
    protected static ?string $modelLabel = 'servidor CQH externo';
    protected static ?string $pluralModelLabel = 'servidores CQH externos';
    protected static ?string $slug = 'plantao-cqh-externos';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Gestão Administrativa';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('nome')->required()->maxLength(255),
            Forms\Components\Select::make('unidade_operacional')
                ->options(['DERF_CONFRESA' => 'DERF Confresa'])
                ->default('DERF_CONFRESA')
                ->required(),
            Forms\Components\TextInput::make('telefone')->tel()->maxLength(50),
            Forms\Components\TextInput::make('ordem')->numeric(),
            Forms\Components\Toggle::make('apto_cqh')->label('Apto CQH')->default(true),
            Forms\Components\Toggle::make('ativo')->default(true),
            Forms\Components\Textarea::make('observacao')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ordem')->sortable(),
                Tables\Columns\TextColumn::make('nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('unidade_operacional')->badge(),
                Tables\Columns\TextColumn::make('telefone')->searchable(),
                Tables\Columns\IconColumn::make('apto_cqh')->boolean(),
                Tables\Columns\IconColumn::make('ativo')->boolean(),
            ])
            ->recordActions([Actions\EditAction::make(), Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePlantaoCqhExternos::route('/')];
    }
}
