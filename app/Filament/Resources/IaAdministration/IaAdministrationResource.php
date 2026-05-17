<?php

namespace App\Filament\Resources\IaAdministration;

use App\Filament\Resources\IaAdministration\Pages\ListIaAdministrations;
use App\Models\InvestigationContext;
use App\Models\User;
use BackedEnum;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class IaAdministrationResource extends Resource
{
    protected static ?string $model = InvestigationContext::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|UnitEnum|null $navigationGroup = 'Administração do Sistema';

    protected static ?string $navigationLabel = 'Administração de IA';

    protected static ?string $slug = 'administracao-ia';

    protected static ?string $modelLabel = 'Administração de IA';

    protected static ?string $pluralModelLabel = 'Administração de IA';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['admin', 'super_admin']);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'analiseInvestigation'])
            ->withCount('aiReports')
            ->withMax('aiReports', 'created_at');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Criado por')
                    ->searchable()
                    ->sortable()
                    ->description(fn (InvestigationContext $record): ?string => $record->user?->email),

                Tables\Columns\TextColumn::make('analiseInvestigation.name')
                    ->label('Investigação')
                    ->searchable()
                    ->limit(42)
                    ->description(fn (InvestigationContext $record): ?string => $record->analise_investigation_id
                        ? '#' . $record->analise_investigation_id . ' - ' . strtoupper((string) $record->analiseInvestigation?->source)
                        : null),

                Tables\Columns\TextColumn::make('arquivo_original')
                    ->label('Contexto/BO')
                    ->placeholder('Sem arquivo')
                    ->limit(34),

                Tables\Columns\TextColumn::make('ai_reports_count')
                    ->label('Relatórios')
                    ->counts('aiReports')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ai_reports_max_created_at')
                    ->label('Último relatório')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Sao_Paulo')
                    ->placeholder('Nenhum')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Sao_Paulo')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Criador')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
            ])
            ->recordActions([
                Actions\Action::make('verRelatorios')
                    ->label('Ver relatórios')
                    ->icon('heroicon-o-document-text')
                    ->modalHeading(fn (InvestigationContext $record): string => 'Relatórios IA do contexto #' . $record->id)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalContent(fn (InvestigationContext $record) => view(
                        'filament.resources.ia-administration.partials.context-reports',
                        ['context' => $record->loadMissing(['user', 'analiseInvestigation', 'aiReports.user'])]
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIaAdministrations::route('/'),
        ];
    }
}
