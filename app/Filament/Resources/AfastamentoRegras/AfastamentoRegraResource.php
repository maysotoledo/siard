<?php

namespace App\Filament\Resources\AfastamentoRegras;

use App\Enums\TipoAfastamento;
use App\Filament\Resources\AfastamentoRegras\Pages;
use App\Models\AfastamentoRegra;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class AfastamentoRegraResource extends Resource
{
    protected static ?string $model = AfastamentoRegra::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel = 'Regras de Afastamento';
    protected static ?string $modelLabel = 'Regra de afastamento';
    protected static ?string $pluralModelLabel = 'Regras de afastamento';
    protected static ?string $slug = 'regras-afastamento';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Gestão Administrativa';
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('tipo_afastamento')->label('Tipo')->required()->options(TipoAfastamento::options()),
            Forms\Components\TextInput::make('nome')->required()->maxLength(255),
            Forms\Components\TextInput::make('dias_por_periodo')->label('Dias por período')->numeric()->required(),
            Forms\Components\TextInput::make('meses_para_aquisicao')->label('Meses para aquisição')->numeric()->required(),
            Forms\Components\Toggle::make('permite_parcelamento')->label('Permite parcelamento'),
            Forms\Components\TextInput::make('quantidade_maxima_parcelas')->label('Máx. parcelas')->numeric()->required(),
            Forms\Components\TextInput::make('dias_minimos_por_parcela')->label('Dias mínimos por parcela')->numeric()->required(),
            Forms\Components\Toggle::make('exige_aprovacao_chefia')->label('Exige aprovação da chefia'),
            Forms\Components\Toggle::make('afeta_efetivo_minimo')->label('Afeta efetivo mínimo'),
            Forms\Components\Toggle::make('permite_interrupcao')->label('Permite interrupção'),
            Forms\Components\Toggle::make('permite_cancelamento_apos_inicio')->label('Permite cancelamento após início'),
            Forms\Components\Toggle::make('devolve_saldo_ao_interromper')->label('Devolve saldo ao interromper'),
            Forms\Components\Toggle::make('ativo')->label('Ativo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('tipo_afastamento')->label('Tipo')->formatStateUsing(fn ($state) => $state?->label())->badge(),
            Tables\Columns\TextColumn::make('nome')->searchable(),
            Tables\Columns\TextColumn::make('dias_por_periodo')->label('Dias'),
            Tables\Columns\TextColumn::make('meses_para_aquisicao')->label('Meses'),
            Tables\Columns\IconColumn::make('ativo')->boolean(),
        ])->recordActions([Actions\EditAction::make(), Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAfastamentoRegras::route('/')];
    }
}
