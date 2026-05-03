<?php

namespace App\Filament\Resources\AfastamentoPeriodosAquisitivos;

use App\Enums\StatusPeriodoAquisitivo;
use App\Enums\TipoAfastamento;
use App\Filament\Resources\AfastamentoPeriodosAquisitivos\Pages;
use App\Models\AfastamentoPeriodoAquisitivo;
use App\Models\User;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class AfastamentoPeriodoAquisitivoResource extends Resource
{
    protected static ?string $model = AfastamentoPeriodoAquisitivo::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel = 'Períodos Aquisitivos';
    protected static ?string $modelLabel = 'Período aquisitivo';
    protected static ?string $pluralModelLabel = 'Períodos aquisitivos';
    protected static ?string $slug = 'periodos-aquisitivos';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Gestão Administrativa';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('user_id')->label('Servidor')->required()->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all())->searchable()->preload(),
            Forms\Components\Select::make('tipo_afastamento')->label('Tipo')->required()->options(TipoAfastamento::options()),
            Forms\Components\DatePicker::make('data_inicio')->label('Início')->required()->native(false)->closeOnDateSelection(),
            Forms\Components\DatePicker::make('data_fim')->label('Fim')->required()->native(false)->closeOnDateSelection(),
            Forms\Components\DatePicker::make('data_aquisicao')->label('Aquisição')->required()->native(false)->closeOnDateSelection(),
            Forms\Components\TextInput::make('dias_direito')->label('Direito')->numeric()->required()->default(30),
            Forms\Components\TextInput::make('dias_usufruidos')->label('Usufruídos')->numeric()->required()->default(0),
            Forms\Components\TextInput::make('dias_disponiveis')->label('Disponíveis')->numeric()->required()->default(0),
            Forms\Components\Select::make('status')->label('Status')->options(StatusPeriodoAquisitivo::options())->default(StatusPeriodoAquisitivo::ADQUIRIDO->value)->required(),
            Forms\Components\Toggle::make('gerado_automaticamente')->label('Gerado automaticamente')->default(false),
            Forms\Components\Textarea::make('observacao')->label('Observação')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Servidor')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tipo_afastamento')->label('Tipo')->formatStateUsing(fn ($state) => $state?->label())->badge(),
                Tables\Columns\TextColumn::make('data_inicio')->label('Início')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('data_fim')->label('Fim')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('data_aquisicao')->label('Aquisição')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('dias_direito')->label('Direito')->sortable(),
                Tables\Columns\TextColumn::make('dias_usufruidos')->label('Usufruídos')->sortable(),
                Tables\Columns\TextColumn::make('dias_disponiveis')->label('Disponíveis')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('Status')->formatStateUsing(fn ($state) => $state?->label())->badge()->color(fn ($state): string => $state?->color() ?? 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')->label('Servidor')->relationship('user', 'name')->searchable()->preload(),
                Tables\Filters\SelectFilter::make('tipo_afastamento')->label('Tipo')->options(TipoAfastamento::options()),
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(StatusPeriodoAquisitivo::options()),
                Tables\Filters\Filter::make('ano')
                    ->label('Ano')
                    ->form([Forms\Components\TextInput::make('ano')->label('Ano')->numeric()->minValue(1900)->maxValue(2100)])
                    ->query(fn ($query, array $data) => $query->when(
                        filled($data['ano'] ?? null),
                        fn ($query) => $query->whereYear('data_inicio', (int) $data['ano']),
                    )),
            ])
            ->recordActions([Actions\EditAction::make(), Actions\DeleteAction::make()])
            ->defaultSort('data_aquisicao', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAfastamentoPeriodosAquisitivos::route('/')];
    }
}
