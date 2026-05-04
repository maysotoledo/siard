<?php

namespace App\Filament\Resources\AfastamentoPrioridadeRegras;

use App\Enums\FuncaoOperacional;
use App\Enums\TipoAfastamento;
use App\Filament\Resources\AfastamentoPrioridadeRegras\Pages;
use App\Models\AfastamentoPrioridadeRegra;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class AfastamentoPrioridadeRegraResource extends Resource
{
    protected static ?string $model = AfastamentoPrioridadeRegra::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';
    protected static ?string $navigationLabel = 'Regras de Prioridade';
    protected static ?string $modelLabel = 'Regra de prioridade';
    protected static ?string $pluralModelLabel = 'Regras de prioridade';
    protected static ?string $slug = 'regras-prioridade-afastamento';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Gestão Administrativa';
    }

    public static function getNavigationSort(): ?int
    {
        return 45;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('nome')->required()->maxLength(255),
            Forms\Components\Select::make('tipo_afastamento')->label('Tipo')->options(TipoAfastamento::options())->placeholder('Todos'),
            Forms\Components\Select::make('funcao_operacional')->label('Função operacional')->options(FuncaoOperacional::options())->placeholder('Todas'),
            Forms\Components\TextInput::make('unidade_id')->label('Unidade ID')->numeric(),
            Forms\Components\Toggle::make('usar_antiguidade_servico_publico')->label('Usar antiguidade no serviço público')->default(true),
            Forms\Components\Toggle::make('usar_antiguidade_carreira')->label('Usar antiguidade na carreira')->default(true),
            Forms\Components\Toggle::make('usar_antiguidade_unidade')->label('Usar antiguidade na unidade')->default(true),
            Forms\Components\TextInput::make('peso_antiguidade_servico_publico')->numeric()->default(2),
            Forms\Components\TextInput::make('peso_antiguidade_carreira')->numeric()->default(3),
            Forms\Components\TextInput::make('peso_antiguidade_unidade')->numeric()->default(1),
            Forms\Components\TextInput::make('peso_periodo_aquisitivo_mais_antigo')->numeric()->default(5),
            Forms\Components\TextInput::make('peso_tempo_sem_gozo')->numeric()->default(5),
            Forms\Components\TextInput::make('peso_saldo_vencido_ou_antigo')->numeric()->default(8),
            Forms\Components\TextInput::make('peso_impacto_operacional')->numeric()->default(-10),
            Forms\Components\Toggle::make('ativo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')->searchable(),
                Tables\Columns\TextColumn::make('tipo_afastamento')->label('Tipo')->formatStateUsing(fn ($state) => $state?->label() ?? 'Todos')->badge(),
                Tables\Columns\TextColumn::make('funcao_operacional')->label('Função')->formatStateUsing(fn ($state) => $state?->label() ?? 'Todas')->badge(),
                Tables\Columns\IconColumn::make('ativo')->boolean(),
            ])
            ->recordActions([Actions\EditAction::make(), Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAfastamentoPrioridadeRegras::route('/')];
    }
}
