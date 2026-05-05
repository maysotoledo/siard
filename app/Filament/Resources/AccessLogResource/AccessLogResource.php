<?php

namespace App\Filament\Resources\AccessLogResource;

use App\Filament\Resources\AccessLogResource\Pages\ListAccessLogs;
use App\Models\AccessLog;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccessLogResource extends Resource
{
    protected static ?string $model = AccessLog::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Logs';
    }

    public static function getNavigationLabel(): string
    {
        return 'Logs de Acesso';
    }

    public static function getModelLabel(): string
    {
        return 'Log de Acesso';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Logs de Acesso';
    }

    public static function getNavigationSort(): ?int
    {
        return 99;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccessLogs::route('/'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('user')
                ->whereIn('event', ['login_success', 'login_failed']))
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Usuário')
                    ->state(fn (AccessLog $record) => $record->user?->name ?? '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('user', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"));
                    })
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->wrap(),

                TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'login_success' => 'Login OK',
                        'login_failed' => 'Login Falhou',
                        default => $state ?? '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'login_success' => 'success',
                        'login_failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('ip')
                    ->label('IP')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('occurred_at')
                    ->label('Data/Hora (GMT-3)')
                    ->dateTime('d/m/Y H:i:s')
                    ->timezone('America/Sao_Paulo')
                    ->sortable(),

                TextColumn::make('user_agent')
                    ->label('User-Agent')
                    ->limit(70)
                    ->tooltip(fn (AccessLog $record) => $record->user_agent)
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Evento')
                    ->options([
                        'login_success' => 'Login OK',
                        'login_failed' => 'Login Falhou',
                    ]),

                SelectFilter::make('user_id')
                    ->label('Usuário')
                    ->options(fn () => User::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()
                    )
                    ->searchable(),

                Filter::make('email')
                    ->label('Email')
                    ->form([
                        TextInput::make('email')
                            ->placeholder('ex: fulano@dominio.com'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $email = trim((string) ($data['email'] ?? ''));
                        return $email === '' ? $query : $query->where('email', 'like', "%{$email}%");
                    }),

                Filter::make('ip')
                    ->label('IP')
                    ->form([
                        TextInput::make('ip')
                            ->placeholder('ex: 200.100.50.10'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $ip = trim((string) ($data['ip'] ?? ''));
                        return $ip === '' ? $query : $query->where('ip', 'like', "%{$ip}%");
                    }),

                Filter::make('periodo')
                    ->label('Período')
                    ->form([
                        DatePicker::make('from')->label('De'),
                        DatePicker::make('until')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('occurred_at', '>=', $from))
                            ->when($data['until'] ?? null, fn (Builder $q, $until) => $q->whereDate('occurred_at', '<=', $until));
                    }),
            ])
            ->emptyStateHeading('Nenhum log encontrado');
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
}
