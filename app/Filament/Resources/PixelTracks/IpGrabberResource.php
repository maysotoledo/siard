<?php

namespace App\Filament\Resources\PixelTracks;

use App\Filament\Resources\PixelTracks\Pages\CreateIpGrabber;
use App\Filament\Resources\PixelTracks\Pages\ListIpGrabbers;
use App\Filament\Resources\PixelTracks\Pages\ViewIpGrabber;
use App\Models\IpGrabber;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class IpGrabberResource extends Resource
{
    protected static ?string $model = IpGrabber::class;

    protected static ?string $slug = 'ip-grabbers';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-eye';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Rastreamento IP';
    }

    public static function getNavigationLabel(): string
    {
        return 'IP Grabber';
    }

    public static function getNavigationSort(): ?int
    {
        return 60;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! (auth()->user()?->hasActivePixelSubscription() ?? false)) {
            return null;
        }

        $count = static::getEloquentQuery()->whereNull('clicked_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getModelLabel(): string
    {
        return 'IP Grabber';
    }

    public static function getPluralModelLabel(): string
    {
        return 'IP Grabber';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIpGrabbers::route('/'),
            'create' => CreateIpGrabber::route('/create'),
            'view' => ViewIpGrabber::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('criador')
            ->where('created_by', auth()->id())
            ->where('tracking_channel', 'link')
            ->latest();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Identificação')
                ->description('Descreva o alvo ou contexto de uso deste IP Grabber.')
                ->components([
                    Forms\Components\TextInput::make('label')
                        ->label('Rótulo / Identificação')
                        ->placeholder('Ex: Suspeito João - e-mail enviado em 07/05/2026')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('preview_tipo')
                        ->label('Tipo de link')
                        ->options([
                            'mensagem' => 'Mensagem do sistema',
                            'noticia' => 'Notícia externa',
                            'pix_bradesco' => 'PIX Bradesco (comprovante)',
                        ])
                        ->default('mensagem')
                        ->selectablePlaceholder(false)
                        ->live()
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\Select::make('tracking_domain')
                        ->label('Domínio do link')
                        ->options([
                            'comprovante-pix.site' => 'comprovante-pix.site',
                            'agenciadanoticia.online' => 'agenciadanoticia.online',
                        ])
                        ->default('comprovante-pix.site')
                        ->selectablePlaceholder(false)
                        ->required(fn (Get $get): bool => $get('preview_tipo') === 'mensagem')
                        ->visible(fn (Get $get): bool => $get('preview_tipo') === 'mensagem')
                        ->helperText('Escolha qual domínio será usado no link gerado para a mensagem do sistema.')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('mensagem')
                        ->label('Mensagem exibida ao clicar')
                        ->options([
                            'Este documento não está mais disponível.' => 'Este documento não está mais disponível.',
                            'Acesso expirado.' => 'Acesso expirado.',
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

                    Forms\Components\Toggle::make('capture_identity')
                        ->label('Tentar coletar identidade digital')
                        ->default(false)
                        ->helperText('Opcional. Nome, e-mail, telefone/autofill e contas/apps detectáveis dependem de autorização, permissões e comportamento do navegador do alvo; em muitos navegadores esses dados podem não ser disponibilizados.')
                        ->columnSpanFull(),
                ]),

            \Filament\Schemas\Components\Section::make('Preview no WhatsApp / Telegram')
                ->description('O que aparece quando o link é colado antes de ser clicado.')
                ->collapsed()
                ->visible(fn (Get $get): bool => ! in_array($get('preview_tipo'), ['noticia', 'pix_bradesco'], true))
                ->components([
                    Forms\Components\TextInput::make('og_titulo')
                        ->label('Título do preview')
                        ->placeholder('Ex: Documento Policial - Delegacia de Confresa')
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
                        ->helperText('Alternativa ao upload. Tamanho ideal: 1200x630px.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

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
                    ->label('URL do link')
                    ->state(fn (IpGrabber $record) => $record->trackingUrl())
                    ->copyable()
                    ->copyMessage('URL copiada!')
                    ->copyableState(fn (IpGrabber $record) => $record->trackingUrl())
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (IpGrabber $record) => $record->clicked_at ? 'Capturado' : 'Aguardando')
                    ->color(fn (IpGrabber $record) => $record->clicked_at ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('total_acessos')
                    ->label('Acessos')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ip')->label('IP Público')->copyable()->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('porta')->label('Porta')->alignCenter()->placeholder('—'),
                Tables\Columns\TextColumn::make('gmt')
                    ->label('GMT')
                    ->formatStateUsing(fn (?string $state): string => $state ? trim(explode(' ', $state)[0]) : '—')
                    ->placeholder('—'),
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
                    ->options(['capturado' => 'Capturado', 'aguardando' => 'Aguardando'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'capturado' => $query->whereNotNull('clicked_at'),
                        'aguardando' => $query->whereNull('clicked_at'),
                        default => $query,
                    }),
                Tables\Filters\Filter::make('ip')
                    ->label('IP')
                    ->form([Forms\Components\TextInput::make('ip')->placeholder('ex: 200.100.50.10')])
                    ->query(fn (Builder $query, array $data) => ($value = trim((string) ($data['ip'] ?? ''))) === '' ? $query : $query->where('ip', 'like', "%{$value}%")),
                Tables\Filters\Filter::make('label')
                    ->label('Identificação')
                    ->form([Forms\Components\TextInput::make('label')->placeholder('Buscar por rótulo...')])
                    ->query(fn (Builder $query, array $data) => ($value = trim((string) ($data['label'] ?? ''))) === '' ? $query : $query->where('label', 'like', "%{$value}%")),
            ])
            ->recordActions([
                Actions\ViewAction::make()->label('Histórico')->icon('heroicon-o-clock'),
                Actions\Action::make('copiar_url')
                    ->label('Copiar URL')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('gray')
                    ->action(function (IpGrabber $record): void {
                        Notification::make()->title('URL copiada')->body($record->trackingUrl())->success()->persistent()->send();
                    }),
                Actions\Action::make('ver_mapa_gps')
                    ->label('GPS')
                    ->icon('heroicon-o-map-pin')
                    ->color('info')
                    ->visible(fn (IpGrabber $record) => $record->gps_latitude !== null)
                    ->url(fn (IpGrabber $record) => "https://www.google.com/maps?q={$record->gps_latitude},{$record->gps_longitude}")
                    ->openUrlInNewTab(),
                Actions\DeleteAction::make()->label('Excluir'),
            ])
            ->emptyStateHeading('Nenhum link criado')
            ->emptyStateDescription('Crie um IP Grabber para gerar um link invisível.')
            ->emptyStateIcon('heroicon-o-eye-slash');
    }
}
