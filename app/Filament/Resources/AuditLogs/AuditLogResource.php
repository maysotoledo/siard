<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogResource extends Resource
{
    use HasPageShield;

    protected static ?string $model = AuditLog::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Logs';
    }

    public static function getNavigationLabel(): string
    {
        return 'Logs de Auditoria';
    }

    public static function getModelLabel(): string
    {
        return 'Log de Auditoria';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Logs de Auditoria';
    }

    public static function getNavigationSort(): ?int
    {
        return 98;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('user')
                ->whereIn('action', ['created', 'updated', 'deleted']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Data/Hora (GMT-3)')
                    ->dateTime('d/m/Y H:i:s')
                    ->timezone('America/Sao_Paulo')
                    ->sortable(),

                TextColumn::make('action')
                    ->label('Ação')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'created' => 'Criou',
                        'updated' => 'Editou',
                        'deleted' => 'Excluiu',
                        default => $state ?? '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'deleted' => 'danger',
                        'updated' => 'warning',
                        'created' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Usuário')
                    ->state(fn (AuditLog $record) => $record->user?->name ?? '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('user', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"));
                    })
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->wrap(),

                TextColumn::make('ip')
                    ->label('IP')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('model_type')
                    ->label('Registro')
                    ->state(fn (AuditLog $record) => self::recordSummary($record))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            $q->where('model_type', 'like', "%{$search}%")
                                ->orWhere('model_id', 'like', "%{$search}%")
                                ->orWhere('meta->model_label', 'like', "%{$search}%")
                                ->orWhere('meta->record_label', 'like', "%{$search}%");
                        });
                    })
                    ->wrap(),

                TextColumn::make('changes')
                    ->label('Criado/Editado/Excluído')
                    ->state(fn (AuditLog $record) => self::changeSummary($record))
                    ->limit(120)
                    ->tooltip(fn (AuditLog $record) => self::changeSummary($record))
                    ->wrap(),

                TextColumn::make('model_id')
                    ->label('ID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('route')
                    ->label('Rota')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('method')
                    ->label('Método')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('url')
                    ->label('URL')
                    ->limit(60)
                    ->tooltip(fn (AuditLog $record) => $record->url)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Ação')
                    ->options([
                        'created' => 'Criou',
                        'updated' => 'Editou',
                        'deleted' => 'Excluiu',
                    ]),

                SelectFilter::make('user_id')
                    ->label('Usuário')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),

                Filter::make('email')
                    ->label('Email')
                    ->form([TextInput::make('email')])
                    ->query(fn (Builder $q, array $data) => ($v = trim((string) ($data['email'] ?? ''))) === '' ? $q : $q->where('email', 'like', "%{$v}%")),

                Filter::make('ip')
                    ->label('IP')
                    ->form([TextInput::make('ip')])
                    ->query(fn (Builder $q, array $data) => ($v = trim((string) ($data['ip'] ?? ''))) === '' ? $q : $q->where('ip', 'like', "%{$v}%")),

                Filter::make('periodo')
                    ->label('Período')
                    ->form([
                        DatePicker::make('from')->label('De'),
                        DatePicker::make('until')->label('Até'),
                    ])
                    ->query(function (Builder $q, array $data) {
                        return $q
                            ->when($data['from'] ?? null, fn (Builder $qq, $from) => $qq->whereDate('occurred_at', '>=', $from))
                            ->when($data['until'] ?? null, fn (Builder $qq, $until) => $qq->whereDate('occurred_at', '<=', $until));
                    }),
            ])
            ->emptyStateHeading('Nenhum log encontrado');
    }

    private static function recordSummary(AuditLog $record): string
    {
        $label = $record->meta['model_label'] ?? null;
        if (! is_string($label) || trim($label) === '') {
            $label = class_basename((string) $record->model_type);
        }

        $recordLabel = $record->meta['record_label'] ?? null;
        $suffix = is_string($recordLabel) && trim($recordLabel) !== ''
            ? " - {$recordLabel}"
            : '';

        return trim($label . ' #' . ($record->model_id ?? '-') . $suffix);
    }

    private static function changeSummary(AuditLog $record): string
    {
        return match ($record->action) {
            'created' => 'Criado: ' . self::formatValues((array) ($record->new_values ?? [])),
            'updated' => 'Editado: ' . self::formatUpdatedValues((array) ($record->old_values ?? []), (array) ($record->new_values ?? [])),
            'deleted' => 'Excluído: ' . self::formatValues((array) ($record->old_values ?? [])),
            default => '—',
        };
    }

    private static function formatUpdatedValues(array $old, array $new): string
    {
        $parts = [];

        foreach ($new as $field => $newValue) {
            $parts[] = "{$field}: " . self::stringValue($old[$field] ?? null) . ' -> ' . self::stringValue($newValue);
        }

        return $parts !== [] ? implode('; ', $parts) : 'sem campos alterados';
    }

    private static function formatValues(array $values): string
    {
        $parts = [];

        foreach ($values as $field => $value) {
            $parts[] = "{$field}: " . self::stringValue($value);
        }

        return $parts !== [] ? implode('; ', $parts) : 'sem valores';
    }

    private static function stringValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[array]';
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : 'vazio';
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
}
