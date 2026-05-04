<?php

namespace App\Filament\Resources\PlantaoCqhServidores;

use App\Filament\Resources\PlantaoCqhServidores\Pages;
use App\Models\PlantaoCqhServidor;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class PlantaoCqhServidorResource extends Resource
{
    protected static ?string $model = PlantaoCqhServidor::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-phone';
    protected static ?string $navigationLabel = 'CQH Geral';
    protected static ?string $slug = 'plantao-cqh';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Gestão Administrativa';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('user_id')->label('Servidor')->relationship('user', 'name')->searchable()->preload()->required(),
            Forms\Components\Hidden::make('unidade_operacional')->default('CONFRESA'),
            Forms\Components\Toggle::make('apto_cqh')->default(true),
            Forms\Components\TextInput::make('ordem')->numeric(),
            Forms\Components\Toggle::make('ativo')->default(true),
            Forms\Components\Textarea::make('observacao')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('ordem')->sortable(),
            Tables\Columns\TextColumn::make('user.name')->label('Servidor')->searchable(),
            Tables\Columns\IconColumn::make('apto_cqh')->boolean(),
            Tables\Columns\IconColumn::make('ativo')->boolean(),
        ])->recordActions([Actions\EditAction::make(), Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePlantaoCqhServidores::route('/')];
    }
}
