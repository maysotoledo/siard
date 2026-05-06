<?php

namespace App\Filament\Resources\AfastamentoRegrasOperacionais;

use App\Enums\FuncaoOperacional;
use App\Filament\Resources\AfastamentoRegrasOperacionais\Pages;
use App\Models\AfastamentoRegraOperacional;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class AfastamentoRegraOperacionalResource extends Resource
{
    protected static ?string $model = AfastamentoRegraOperacional::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Regras Operacionais';
    protected static ?string $modelLabel = 'Regra operacional';
    protected static ?string $pluralModelLabel = 'Regras operacionais';
    protected static ?string $slug = 'regras-operacionais-afastamento';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Gestão Administrativa';
    }

    public static function getNavigationSort(): ?int
    {
        return 40;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('funcao_operacional')
                ->label('Função operacional')
                ->options(FuncaoOperacional::options())
                ->searchable(),
            Forms\Components\Select::make('grupo_operacional')
                ->label('Grupo operacional')
                ->options(['expediente' => 'Expediente', 'plantao' => 'Plantão']),
            Forms\Components\TextInput::make('minimo_disponivel')
                ->label('Mínimo disponível')
                ->numeric()
                ->default(1),
            Forms\Components\Toggle::make('prioridade_operacional')
                ->label('Prioridade operacional')
                ->default(false),
            Forms\Components\TagsInput::make('permite_cobertura_por_funcao')
                ->label('Permite cobertura por função')
                ->suggestions(array_keys(FuncaoOperacional::options()))
                ->columnSpanFull(),
            Forms\Components\TextInput::make('minimo_por_dia')
                ->label('Mínimo por dia')
                ->numeric()
                ->required()
                ->default(1),
            Forms\Components\TextInput::make('maximo_afastados_simultaneos')
                ->label('Máx. simultâneos')
                ->numeric()
                ->required()
                ->default(1),
            Forms\Components\KeyValue::make('dias_criticos')
                ->label('Dias críticos')
                ->keyLabel('Data')
                ->valueLabel('Motivo')
                ->columnSpanFull(),
            Forms\Components\Toggle::make('ativo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('funcao_operacional')
                ->label('Função operacional')
                ->formatStateUsing(fn ($state) => $state?->label() ?? '-')
                ->badge(),
            Tables\Columns\TextColumn::make('grupo_operacional')->label('Grupo')->badge(),
            Tables\Columns\TextColumn::make('minimo_disponivel')->label('Mín. disponível'),
            Tables\Columns\IconColumn::make('prioridade_operacional')->label('Prioridade')->boolean(),
            Tables\Columns\TextColumn::make('minimo_por_dia')->label('Mín.'),
            Tables\Columns\TextColumn::make('maximo_afastados_simultaneos')->label('Máx.'),
            Tables\Columns\IconColumn::make('ativo')->boolean(),
        ])->recordActions([Actions\EditAction::make(), Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAfastamentoRegrasOperacionais::route('/')];
    }
}
