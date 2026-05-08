<?php

namespace App\Filament\Resources\PixelTracks;

use App\Filament\Resources\PixelTracks\Pages\CreatePixelTrack;
use App\Filament\Resources\PixelTracks\Pages\ListPixelTracks;
use App\Filament\Resources\PixelTracks\Pages\ViewPixelTrack;
use App\Models\PixelTrack;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource do usuário — exibe e gerencia apenas os próprios pixels.
 */
class PixelTrackResource extends Resource
{
    protected static ?string $model = PixelTrack::class;

    protected static ?string $slug = 'pixel-tracks';

    // ─── Navegação ─────────────────────────────────────────────────────────────

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-eye';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Investigação Telemática';
    }

    public static function getNavigationLabel(): string
    {
        return 'Pixel Tracker';
    }

    public static function getNavigationSort(): ?int
    {
        return 60;
    }

    /** Badge mostra quantos pixels próprios ainda estão aguardando captura. */
    public static function getNavigationBadge(): ?string
    {
        $count = PixelTrack::query()
            ->where('created_by', auth()->id())
            ->whereNull('clicked_at')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    // ─── Labels ────────────────────────────────────────────────────────────────

    public static function getModelLabel(): string
    {
        return 'Pixel de Rastreamento';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pixels de Rastreamento';
    }

    // ─── Páginas ───────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => ListPixelTracks::route('/'),
            'create' => CreatePixelTrack::route('/create'),
            'view'   => ViewPixelTrack::route('/{record}'),
        ];
    }

    // ─── Controle de acesso ────────────────────────────────────────────────────

    /** Cada usuário vê e gerencia apenas os pixels que ele mesmo criou. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('criador')
            ->where('created_by', auth()->id())
            ->latest();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    // ─── Formulário de criação ─────────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Identificação')
                ->description('Descreva o alvo ou contexto de uso deste pixel.')
                ->components([
                    Forms\Components\TextInput::make('label')
                        ->label('Rótulo / Identificação')
                        ->placeholder('Ex: Suspeito João — e-mail enviado em 07/05/2026')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('preview_tipo')
                        ->label('Tipo de link')
                        ->options([
                            'mensagem'      => 'Mensagem do sistema',
                            'noticia'       => 'Notícia externa',
                            'pix_bradesco'  => 'PIX Bradesco (comprovante)',
                        ])
                        ->default('mensagem')
                        ->selectablePlaceholder(false)
                        ->live()
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\Select::make('mensagem')
                        ->label('Mensagem exibida ao clicar')
                        ->options([
                            'Este documento não está mais disponível.' => 'Este documento não está mais disponível.',
                            'Acesso expirado.'                         => 'Acesso expirado.',
                        ])
                        ->default('Este documento não está mais disponível.')
                        ->selectablePlaceholder(false)
                        ->required()
                        ->visible(fn (Get $get): bool => ! in_array($get('preview_tipo'), ['noticia', 'pix_bradesco'], true))
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('noticia_url')
                        ->label('Link da notícia')
                        ->placeholder('https://site.com/noticia...')
                        ->url()
                        ->maxLength(255)
                        ->required(fn (Get $get): bool => $get('preview_tipo') === 'noticia')
                        ->visible(fn (Get $get): bool => $get('preview_tipo') === 'noticia')
                        ->helperText('O sistema usará título, descrição e imagem da notícia no preview. Ao clicar, o alvo será encaminhado para esta URL.')
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('capture_gps')
                        ->label('Solicitar GPS do alvo')
                        ->default(false)
                        ->helperText('Depende de autorização explícita do alvo no navegador. Pode comprometer a discrição da coleta.')
                        ->columnSpanFull(),
                ]),

            \Filament\Schemas\Components\Section::make('Preview no WhatsApp / Telegram')
                ->description('O que aparece quando o link é colado antes de ser clicado.')
                ->collapsed()
                ->visible(fn (Get $get): bool => ! in_array($get('preview_tipo'), ['noticia', 'pix_bradesco'], true))
                ->components([
                    Forms\Components\TextInput::make('og_titulo')
                        ->label('Título do preview')
                        ->placeholder('Ex: Documento Policial — Delegacia de Confresa')
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('og_descricao')
                        ->label('Descrição do preview')
                        ->placeholder('Ex: Clique para visualizar o documento.')
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('og_imagem_upload')
                        ->label('Upload de imagem do preview')
                        ->helperText('JPEG ou PNG. Tem prioridade sobre a URL abaixo.')
                        ->image()
                        ->disk('public')
                        ->directory('pixel-og')
                        ->visibility('public')
                        ->imagePreviewHeight('120')
                        ->maxSize(4096)
                        ->acceptedFileTypes(['image/jpeg', 'image/png'])
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('og_imagem')
                        ->label('Ou informe a URL da imagem do preview')
                        ->placeholder('https://seudominio.com/imagens/preview.jpg')
                        ->url()
                        ->helperText('Alternativa ao upload. Tamanho ideal: 1200×630px.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    // ─── Tabela do usuário ─────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Identificação')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('pixel_url')
                    ->label('URL do Pixel')
                    ->state(fn (PixelTrack $r) => route('pixel.track', $r->token))
                    ->copyable()
                    ->copyMessage('URL copiada!')
                    ->copyableState(fn (PixelTrack $r) => route('pixel.track', $r->token))
                    ->wrap(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (PixelTrack $r) => $r->clicked_at ? 'Capturado' : 'Aguardando')
                    ->color(fn (PixelTrack $r) => $r->clicked_at ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('total_acessos')
                    ->label('Acessos')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip')
                    ->label('IP Público')
                    ->copyable()
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('porta')
                    ->label('Porta')
                    ->alignCenter()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('gmt')
                    ->label('GMT')
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? trim(explode(' ', $state)[0])
                        : '—')
                    ->placeholder('—'),

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

                Tables\Columns\TextColumn::make('clicked_at')
                    ->label('Hora do Acesso')
                    ->dateTime('d/m/Y H:i:s')
                    ->timezone('America/Sao_Paulo')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Sao_Paulo')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
            ])
            ->recordActions([
                Actions\ViewAction::make()
                    ->label('Histórico')
                    ->icon('heroicon-o-clock'),

                Actions\Action::make('copiar_url')
                    ->label('Copiar URL')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('gray')
                    ->action(function (PixelTrack $record): void {
                        $url = route('pixel.track', $record->token);

                        Notification::make()
                            ->title('URL do pixel copiada')
                            ->body($url)
                            ->success()
                            ->persistent()
                            ->send();
                    }),

                // Actions\Action::make('copiar_img_tag')
                //     ->label('Tag <img>')
                //     ->icon('heroicon-o-code-bracket')
                //     ->color('gray')
                //     ->action(function (PixelTrack $record): void {
                //         $url    = route('pixel.gif', $record->token);
                //         $imgTag = "<img src=\"{$url}\" width=\"1\" height=\"1\" alt=\"\" style=\"display:none\">";

                //         Notification::make()
                //             ->title('Tag HTML do Pixel (e-mail)')
                //             ->body($imgTag)
                //             ->info()
                //             ->persistent()
                //             ->send();
                //     }),

                Actions\Action::make('ver_mapa_gps')
                    ->label('GPS')
                    ->icon('heroicon-o-map-pin')
                    ->color('info')
                    ->visible(fn (PixelTrack $r) => $r->gps_latitude !== null)
                    ->url(fn (PixelTrack $r) => "https://www.google.com/maps?q={$r->gps_latitude},{$r->gps_longitude}")
                    ->openUrlInNewTab(),

                Actions\DeleteAction::make()
                    ->label('Excluir'),
            ])
            ->emptyStateHeading('Nenhum pixel criado')
            ->emptyStateDescription('Crie um pixel de rastreamento para gerar um link invisível.')
            ->emptyStateIcon('heroicon-o-eye-slash');
    }
}
