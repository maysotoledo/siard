<?php

namespace App\Filament\Resources\PixelTracks;

use App\Filament\Resources\PixelTracks\Pages\CreatePixelTrack;
use App\Filament\Resources\PixelTracks\Pages\ListPixelTracks;
use App\Models\PixelTrack;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PixelTrackResource extends Resource
{
    protected static ?string $model = PixelTrack::class;

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

    public static function getModelLabel(): string
    {
        return 'Pixel de Rastreamento';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pixels de Rastreamento';
    }

    public static function getNavigationSort(): ?int
    {
        return 60;
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPixelTracks::route('/'),
            'create' => CreatePixelTrack::route('/create'),
        ];
    }

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

                    Forms\Components\Select::make('mensagem')
                        ->label('Mensagem exibida ao clicar')
                        ->options([
                            'Este documento não está mais disponível.' => 'Este documento não está mais disponível.',
                            'Acesso expirado.'                         => 'Acesso expirado.',
                        ])
                        ->default('Este documento não está mais disponível.')
                        ->selectablePlaceholder(false)
                        ->required()
                        ->columnSpanFull(),
                ]),

            \Filament\Schemas\Components\Section::make('Preview no WhatsApp / Telegram')
                ->description('O que aparece automaticamente quando o link é colado antes de ser clicado.')
                ->collapsed()
                ->components([
                    Forms\Components\TextInput::make('og_titulo')
                        ->label('Título do preview')
                        ->placeholder('Ex: Documento Policial — Delegacia de Confresa')
                        ->maxLength(100)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('og_descricao')
                        ->label('Descrição do preview')
                        ->placeholder('Ex: Clique para visualizar o documento.')
                        ->maxLength(200)
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('og_imagem_upload')
                        ->label('Upload de imagem do preview')
                        ->helperText('Faça upload de uma imagem. Se preenchido, tem prioridade sobre a URL abaixo.')
                        ->image()
                        ->disk('public')
                        ->directory('pixel-og')
                        ->visibility('public')
                        ->imagePreviewHeight('120')
                        ->maxSize(4096)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
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

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('criador')->latest())
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
                    ->state(fn (PixelTrack $record) => $record->clicked_at ? 'Capturado' : 'Aguardando')
                    ->color(fn (PixelTrack $record) => $record->clicked_at ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('total_acessos')
                    ->label('Acessos')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip')
                    ->label('IP Público')
                    ->copyable()
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('ip_local')
                    ->label('IP Local (WebRTC)')
                    ->copyable()
                    ->placeholder('—')
                    ->tooltip('IP privado do dispositivo — identifica o cliente junto ao provedor em ambiente CGN'),

                Tables\Columns\TextColumn::make('porta')
                    ->label('Porta')
                    ->alignCenter()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('gmt')
                    ->label('GMT / Fuso')
                    ->placeholder('—')
                    ->wrap(),

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

                Tables\Columns\TextColumn::make('localizacao')
                    ->label('Localização')
                    ->placeholder('—')
                    ->state(fn (PixelTrack $r) => implode(', ', array_filter([
                        $r->cidade,
                        $r->regiao,
                        $r->pais,
                    ])) ?: null),

                Tables\Columns\TextColumn::make('coordenadas')
                    ->label('Coordenadas')
                    ->placeholder('—')
                    ->state(fn (PixelTrack $r) => $r->latitude !== null
                        ? "{$r->latitude}, {$r->longitude}"
                        : null
                    ),

                Tables\Columns\TextColumn::make('isp')
                    ->label('ISP / Operadora')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('user_agent')
                    ->label('User-Agent')
                    ->limit(60)
                    ->tooltip(fn (PixelTrack $r) => $r->user_agent)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('clicked_at')
                    ->label('Primeiro Acesso')
                    ->dateTime('d/m/Y H:i:s')
                    ->timezone('America/Sao_Paulo')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('criador.name')
                    ->label('Criado por')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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

                Actions\Action::make('ver_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map-pin')
                    ->color('success')
                    ->visible(fn (PixelTrack $r) => $r->latitude !== null)
                    ->url(fn (PixelTrack $r) => "https://www.google.com/maps?q={$r->latitude},{$r->longitude}")
                    ->openUrlInNewTab(),

                Actions\DeleteAction::make()
                    ->label('Excluir'),
            ])
            ->emptyStateHeading('Nenhum pixel criado')
            ->emptyStateDescription('Crie um pixel de rastreamento para gerar um link invisível.')
            ->emptyStateIcon('heroicon-o-eye-slash');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}
