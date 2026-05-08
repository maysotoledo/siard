<?php

namespace App\Filament\Resources\PixelTracks;

use App\Filament\Resources\PixelTracks\Pages\ListTodosPixelTracks;
use App\Filament\Resources\PixelTracks\Pages\ViewTodosPixelTrack;
use App\Models\PixelTrack;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource de administração — visível somente para super_admin ou usuários
 * com a permissão Shield "ViewAny:TodosPixelTrack".
 * Exibe todos os pixels de todos os usuários, com colunas e filtros estendidos.
 */
class TodosPixelTracksResource extends Resource
{
    protected static ?string $model = PixelTrack::class;

    protected static ?string $slug = 'todos-pixel-tracks';

    // ─── Navegação ─────────────────────────────────────────────────────────────

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Investigação Telemática';
    }

    public static function getNavigationLabel(): string
    {
        return 'Pixels (Administração)';
    }

    public static function getNavigationSort(): ?int
    {
        return 61;
    }

    /** Badge mostra o total geral de pixels aguardando captura. */
    public static function getNavigationBadge(): ?string
    {
        if (! static::canViewAny()) {
            return null;
        }

        $count = PixelTrack::query()->whereNull('clicked_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    // ─── Labels ────────────────────────────────────────────────────────────────

    public static function getModelLabel(): string
    {
        return 'Pixel (Admin)';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pixels de Rastreamento — Administração';
    }

    // ─── Páginas ───────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => ListTodosPixelTracks::route('/'),
            'view'  => ViewTodosPixelTrack::route('/{record}'),
        ];
    }

    // ─── Controle de acesso ────────────────────────────────────────────────────

    /**
     * Acesso restrito a super_admin ou usuários com permissão Shield
     * "ViewAny:TodosPixelTrack" (criada via painel Shield › Permissões Personalizadas).
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && (
            $user->hasRole('super_admin') ||
            $user->can('ViewAny:TodosPixelTrack')
        );
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    /** Criação de pixel feita apenas pelo resource do usuário. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Edição desabilitada — pixels são imutáveis após a criação. */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /** Exclusão permitida a super_admin ou quem tiver Delete:PixelTrack. */
    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();

        return $user !== null && (
            $user->hasRole('super_admin') ||
            $user->can('Delete:PixelTrack')
        );
    }

    // ─── Query ─────────────────────────────────────────────────────────────────

    /** Sem filtro de usuário — exibe todos os pixels do sistema. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('criador')
            ->latest();
    }

    // ─── Tabela administrativa ─────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                // ── Autoria ──────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('criador.name')
                    ->label('Criado por')
                    ->searchable(query: fn (Builder $q, string $search) => $q->whereHas(
                        'criador', fn (Builder $u) => $u->where('name', 'like', "%{$search}%")
                    ))
                    ->sortable()
                    ->weight('bold')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('label')
                    ->label('Identificação')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                // ── Pixel ────────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('preview_tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'noticia' => 'Notícia',
                        default   => 'Mensagem',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'noticia' => 'success',
                        default   => 'gray',
                    }),

                Tables\Columns\TextColumn::make('pixel_url')
                    ->label('URL do Pixel')
                    ->state(fn (PixelTrack $r) => route('pixel.track', $r->token))
                    ->copyable()
                    ->copyMessage('URL copiada!')
                    ->copyableState(fn (PixelTrack $r) => route('pixel.track', $r->token))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                // ── Captura ──────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (PixelTrack $r) => $r->clicked_at ? 'Capturado' : 'Aguardando')
                    ->color(fn (PixelTrack $r) => $r->clicked_at ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('total_acessos')
                    ->label('Acessos')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('clicked_at')
                    ->label('1º Acesso')
                    ->dateTime('d/m/Y H:i:s')
                    ->timezone('America/Sao_Paulo')
                    ->placeholder('—')
                    ->sortable(),

                // ── Dados de rede ────────────────────────────────────────────
                Tables\Columns\TextColumn::make('ip')
                    ->label('IP Público')
                    ->copyable()
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('porta')
                    ->label('Porta')
                    ->alignCenter()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('isp')
                    ->label('ISP / Operadora')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ── Localização ──────────────────────────────────────────────
                Tables\Columns\TextColumn::make('localizacao')
                    ->label('Localização IP')
                    ->state(fn (PixelTrack $r) => implode(', ', array_filter([
                        $r->cidade, $r->regiao, $r->pais,
                    ])) ?: '—')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('gmt')
                    ->label('GMT')
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? trim(explode(' ', $state)[0])
                        : '—')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ── Dispositivo ──────────────────────────────────────────────
                Tables\Columns\TextColumn::make('plataforma')
                    ->label('Plataforma')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('idioma')
                    ->label('Idioma')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('resolucao')
                    ->label('Resolução')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ── Metadados ────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Sao_Paulo')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('criador')
                    ->label('Criado por')
                    ->relationship('criador', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'capturado'  => 'Capturado',
                        'aguardando' => 'Aguardando',
                    ])
                    ->query(fn (Builder $q, array $data) => match ($data['value'] ?? null) {
                        'capturado'  => $q->whereNotNull('clicked_at'),
                        'aguardando' => $q->whereNull('clicked_at'),
                        default      => $q,
                    }),

                Tables\Filters\SelectFilter::make('preview_tipo')
                    ->label('Tipo de pixel')
                    ->options([
                        'mensagem' => 'Mensagem do sistema',
                        'noticia'  => 'Notícia externa',
                    ]),

                Tables\Filters\Filter::make('ip')
                    ->label('IP')
                    ->form([
                        Forms\Components\TextInput::make('ip')
                            ->placeholder('ex: 200.100.50.10'),
                    ])
                    ->query(fn (Builder $q, array $data) => ($v = trim((string) ($data['ip'] ?? ''))) === ''
                        ? $q : $q->where('ip', 'like', "%{$v}%")),

                Tables\Filters\Filter::make('label')
                    ->label('Identificação')
                    ->form([
                        Forms\Components\TextInput::make('label')
                            ->placeholder('Buscar por rótulo...'),
                    ])
                    ->query(fn (Builder $q, array $data) => ($v = trim((string) ($data['label'] ?? ''))) === ''
                        ? $q : $q->where('label', 'like', "%{$v}%")),

                Tables\Filters\Filter::make('periodo')
                    ->label('Período de criação')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn (Builder $qq, $d) => $qq->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $qq, $d) => $qq->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                Actions\ViewAction::make()
                    ->label('Histórico')
                    ->icon('heroicon-o-clock'),

                Actions\Action::make('copiar_img_tag')
                    ->label('Tag <img>')
                    ->icon('heroicon-o-code-bracket')
                    ->color('gray')
                    ->action(function (PixelTrack $record): void {
                        $url    = route('pixel.gif', $record->token);
                        $imgTag = "<img src=\"{$url}\" width=\"1\" height=\"1\" alt=\"\" style=\"display:none\">";

                        Notification::make()
                            ->title('Tag HTML do Pixel (e-mail)')
                            ->body($imgTag)
                            ->info()
                            ->persistent()
                            ->send();
                    }),

                Actions\Action::make('ver_mapa_gps')
                    ->label('GPS')
                    ->icon('heroicon-o-map-pin')
                    ->color('info')
                    ->visible(fn (PixelTrack $r) => $r->gps_latitude !== null)
                    ->url(fn (PixelTrack $r) => "https://www.google.com/maps?q={$r->gps_latitude},{$r->gps_longitude}")
                    ->openUrlInNewTab(),

                Actions\DeleteAction::make()
                    ->label('Excluir')
                    ->visible(fn (PixelTrack $record) => static::canDelete($record)),
            ])
            ->emptyStateHeading('Nenhum pixel encontrado')
            ->emptyStateIcon('heroicon-o-shield-exclamation');
    }
}
