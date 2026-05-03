<?php

namespace App\Filament\Resources\AfastamentoPeriodosBloqueados;

use App\Enums\TipoAfastamento;
use App\Filament\Resources\AfastamentoPeriodosBloqueados\Pages;
use App\Models\AfastamentoPeriodoBloqueado;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class AfastamentoPeriodoBloqueadoResource extends Resource
{
    protected static ?string $model = AfastamentoPeriodoBloqueado::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';
    protected static ?string $navigationLabel = 'Períodos Bloqueados';
    protected static ?string $modelLabel = 'Período bloqueado';
    protected static ?string $pluralModelLabel = 'Períodos bloqueados';
    protected static ?string $slug = 'periodos-bloqueados-afastamento';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Gestão Administrativa';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('unidade_id')->label('Unidade ID')->numeric(),
            Forms\Components\Select::make('tipo_afastamento')->label('Tipo')->options(['' => 'Todos'] + TipoAfastamento::options()),
            Forms\Components\DatePicker::make('data_inicio')->label('Início')->required()->native(false),
            Forms\Components\DatePicker::make('data_fim')->label('Fim')->required()->native(false),
            Forms\Components\TextInput::make('motivo')->required()->maxLength(255),
            Forms\Components\Toggle::make('bloqueio_total')->label('Bloqueio total')->default(true),
            Forms\Components\TagsInput::make('funcoes_afetadas')->label('Funções afetadas'),
            Forms\Components\Toggle::make('ativo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('tipo_afastamento')->label('Tipo')->formatStateUsing(fn ($state) => $state?->label() ?? 'Todos')->badge(),
            Tables\Columns\TextColumn::make('data_inicio')->label('Início')->date('d/m/Y')->sortable(),
            Tables\Columns\TextColumn::make('data_fim')->label('Fim')->date('d/m/Y')->sortable(),
            Tables\Columns\TextColumn::make('motivo')->searchable()->limit(60),
            Tables\Columns\IconColumn::make('bloqueio_total')->boolean(),
            Tables\Columns\IconColumn::make('ativo')->boolean(),
        ])->recordActions([Actions\EditAction::make(), Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAfastamentoPeriodosBloqueados::route('/')];
    }
}
