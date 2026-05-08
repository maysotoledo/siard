<?php

namespace App\Filament\Resources\PixelAdmin;

use App\Filament\Resources\PixelAdmin\Pages\CreatePixelAdmin;
use App\Filament\Resources\PixelAdmin\Pages\EditPixelAdmin;
use App\Filament\Resources\PixelAdmin\Pages\ListPixelAdmins;
use App\Models\PixelSubscription;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PixelAdminResource extends Resource
{
    protected static ?string $model = PixelSubscription::class;

    protected static ?string $slug = 'painel-admin-pixel';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-credit-card';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Investigação Telemática';
    }

    public static function getNavigationLabel(): string
    {
        return 'Painel Admin Recebimentos';
    }

    public static function getNavigationSort(): ?int
    {
        return 62;
    }

    public static function getModelLabel(): string
    {
        return 'Acesso Mensal aos Rastreadores';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Painel Admin Recebimentos';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) $user && $user->hasAnyRole(['admin', 'super_admin']);
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'releasedBy'])
            ->latest('updated_at');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Mensalidade dos Rastreadores')
                ->components([
                    Forms\Components\Select::make('user_id')
                        ->label('Usuário')
                        ->relationship(
                            name: 'user',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query) => $query->orderBy('name'),
                        )
                        ->getOptionLabelFromRecordUsing(fn (User $record): string => "{$record->name} ({$record->email})")
                        ->searchable()
                        ->preload()
                        ->required()
                        ->unique(ignoreRecord: true),

                    Forms\Components\DateTimePicker::make('paid_at')
                        ->label('Data do pagamento')
                        ->seconds(false),

                    Forms\Components\DatePicker::make('expires_at')
                        ->label('Data de expiração')
                        ->required(),

                    Forms\Components\Toggle::make('access_enabled')
                        ->label('Liberar acesso aos rastreadores')
                        ->default(true)
                        ->helperText('Uma única assinatura ativa libera IP Grabber e Tracker de E-mail.'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Observações')
                        ->rows(4)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuário')
                    ->searchable()
                    ->sortable()
                    ->description(fn (PixelSubscription $record): ?string => $record->user?->email),

                Tables\Columns\IconColumn::make('access_enabled')
                    ->label('Liberado')
                    ->boolean(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Pagamento')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Sao_Paulo')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expira em')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (PixelSubscription $record): string => match (true) {
                        ! $record->access_enabled => 'Bloqueado',
                        $record->isActive() => 'Ativo',
                        default => 'Expirado',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Ativo' => 'success',
                        'Bloqueado' => 'gray',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('releasedBy.name')
                    ->label('Liberado por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('released_at')
                    ->label('Liberação')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Sao_Paulo')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Observações')
                    ->wrap()
                    ->limit(80)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Ativo',
                        'expired' => 'Expirado',
                        'blocked' => 'Bloqueado',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'active' => $query->where('access_enabled', true)->whereDate('expires_at', '>=', now()->toDateString()),
                            'expired' => $query->whereDate('expires_at', '<', now()->toDateString()),
                            'blocked' => $query->where('access_enabled', false),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\CreateAction::make(),
            ])
            ->emptyStateHeading('Nenhuma mensalidade cadastrada')
            ->emptyStateDescription('Cadastre aqui os usuários liberados para usar IP Grabber e Tracker de E-mail.')
            ->emptyStateIcon('heroicon-o-credit-card');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPixelAdmins::route('/'),
            'create' => CreatePixelAdmin::route('/create'),
            'edit' => EditPixelAdmin::route('/{record}/edit'),
        ];
    }
}
