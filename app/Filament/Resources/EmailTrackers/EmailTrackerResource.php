<?php

namespace App\Filament\Resources\EmailTrackers;

use App\Filament\Resources\EmailTrackers\Pages\CreateEmailTracker;
use App\Filament\Resources\EmailTrackers\Pages\ListEmailTrackers;
use App\Filament\Resources\EmailTrackers\Pages\ViewEmailTracker;
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
use Illuminate\Support\HtmlString;

class EmailTrackerResource extends Resource
{
    protected static ?string $model = IpGrabber::class;

    protected static ?string $slug = 'email-trackers';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-envelope';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Rastreamento IP';
    }

    public static function getNavigationLabel(): string
    {
        return 'Tracker de E-mail';
    }

    public static function getNavigationSort(): ?int
    {
        return 63;
    }

    public static function getModelLabel(): string
    {
        return 'Tracker de E-mail';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Trackers de E-mail';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailTrackers::route('/'),
            'create' => CreateEmailTracker::route('/create'),
            'view' => ViewEmailTracker::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('criador')
            ->where('created_by', auth()->id())
            ->where('tracking_channel', 'email')
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
                        ->description('Informe o contexto interno deste disparo.')
                        ->icon('heroicon-o-identification')
                        ->components([
                            Forms\Components\TextInput::make('label')
                                ->label('Identificação')
                                ->placeholder('Ex: Campanha maio - suspeito João')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                        ]),

                    \Filament\Schemas\Components\Wizard\Step::make('Tipo de e-mail')
                        ->description('Escolha o modelo e o destino do envio.')
                        ->icon('heroicon-o-envelope')
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                        ])
                        ->components([
                            Forms\Components\Radio::make('email_tipo')
                                ->label('Modelo')
                                ->options([
                                    IpGrabber::EMAIL_TYPE_MARKETING        => 'E-mail Padrão do Sistema',
                                    IpGrabber::EMAIL_TYPE_RECOVERY          => 'E-mail de recuperação',
                                    IpGrabber::EMAIL_TYPE_PASSWORD_RESET    => 'Tentativa de redefinição de senha',
                                    IpGrabber::EMAIL_TYPE_PASSWORD_CHANGED  => 'Alteração de senha',
                                ])
                                ->descriptions([
                                    IpGrabber::EMAIL_TYPE_MARKETING        => 'Notificação padrão de documento disponível para visualização.',
                                    IpGrabber::EMAIL_TYPE_RECOVERY          => 'Aviso de segurança sobre alteração de senha com layout Google.',
                                    IpGrabber::EMAIL_TYPE_PASSWORD_RESET    => 'Notificação de tentativa de redefinição de senha bloqueada, com layout Google.',
                                    IpGrabber::EMAIL_TYPE_PASSWORD_CHANGED  => 'Confirmação de alteração de senha com link de proteção da conta.',
                                ])
                                ->default(IpGrabber::EMAIL_TYPE_MARKETING)
                                ->required()
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('target_email')
                                ->label('E-mail para envio')
                                ->placeholder('alvo@exemplo.com')
                                ->email()
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('recovery_email')
                                ->label('E-mail de recuperação identificado')
                                ->placeholder('recuperacao@exemplo.com')
                                ->email()
                                ->required(fn (Get $get): bool => $get('email_tipo') === IpGrabber::EMAIL_TYPE_RECOVERY)
                                ->visible(fn (Get $get): bool => $get('email_tipo') === IpGrabber::EMAIL_TYPE_RECOVERY)
                                ->maxLength(255)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('nome_alvo')
                                ->label('Nome do alvo')
                                ->placeholder('Ex: João Silva')
                                ->visible(fn (Get $get): bool => in_array($get('email_tipo'), [IpGrabber::EMAIL_TYPE_RECOVERY, IpGrabber::EMAIL_TYPE_PASSWORD_RESET]))
                                ->maxLength(255)
                                ->helperText('Usado na saudação "Caro [Nome]". Deixe em branco para omitir o nome.')
                                ->columnSpanFull(),
                        ]),

                    \Filament\Schemas\Components\Wizard\Step::make('Revisão')
                        ->description('Confira o conteúdo antes do envio.')
                        ->icon('heroicon-o-eye')
                        ->components([
                            Forms\Components\Placeholder::make('preview_email')
                                ->hiddenLabel()
                                ->content(fn (Get $get): HtmlString => static::renderEmailPreview($get))
                                ->columnSpanFull(),
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
                . '<span class="fi-btn-label">Enviar e-mail</span>'
            . '</button>'
        );
    }

    private static function renderEmailPreview(Get $get): HtmlString
    {
        $type          = (string) ($get('email_tipo') ?: IpGrabber::EMAIL_TYPE_MARKETING);
        $targetEmail   = trim((string) $get('target_email'));
        $recoveryEmail = trim((string) $get('recovery_email'));
        $nomeAlvo      = trim((string) $get('nome_alvo'));
        $label         = trim((string) $get('label'));
        $isReset       = $type === IpGrabber::EMAIL_TYPE_PASSWORD_RESET;
        $isRecovery    = $type === IpGrabber::EMAIL_TYPE_RECOVERY;
        $isChanged     = $type === IpGrabber::EMAIL_TYPE_PASSWORD_CHANGED;
        $googleLogo    = '<span style=”font-weight:700;letter-spacing:-.5px;font-size:15px;”><span style=”color:#4285F4”>G</span><span style=”color:#EA4335”>o</span><span style=”color:#FBBC05”>o</span><span style=”color:#4285F4”>g</span><span style=”color:#34A853”>l</span><span style=”color:#EA4335”>e</span></span>';

        if ($isChanged) {
            $emailAlvo = $targetEmail !== '' ? e($targetEmail) : '[e-mail do alvo]';
            $title     = $googleLogo . ' — Sua senha foi alterada';
            $body      = '<p style=”margin:0 0 10px;”>Isso é uma confirmação de que a senha da conta <strong>' . $emailAlvo . '</strong> foi alterada.</p>'
                . '<p style=”margin:0 0 14px;”>Se você não alterou sua senha, proteja sua conta clicando no botão abaixo.</p>'
                . '<span style=”display:inline-block;background:#1a73e8;color:#fff;padding:8px 18px;border-radius:4px;font-size:13px;font-weight:500;”>Proteger minha conta</span>'
                . '<p style=”margin:10px 0 0;font-size:12px;color:#5f6368;”>Se estiver tendo problemas, consulte a <u>central de ajuda</u>.</p>'
                . '<p style=”margin:10px 0 0;font-size:12px;color:#5f6368;”>Suporte da Google SIARD</p>';
        } elseif ($isReset) {
            $saudacao  = $nomeAlvo !== '' ? 'Caro ' . e($nomeAlvo) . ',' : 'Prezado(a),';
            $emailAlvo = $targetEmail !== '' ? e($targetEmail) : '[e-mail do alvo]';
            $title     = $googleLogo . ' — Aviso de segurança do e-mail';
            $body      = '<p style=”margin:0 0 10px;”>' . $saudacao . '</p>'
                . '<p style=”margin:0 0 10px;”>Não conseguimos redefinir a senha da sua conta Google (<strong>' . $emailAlvo . '</strong>) porque houve muitas tentativas malsucedidas de responder às suas perguntas de segurança. Para proteger a segurança da sua conta, você não poderá redefinir sua senha nas próximas oito horas.</p>'
                . '<p style=”margin:0 0 14px;”>Se você não fez essa alteração ou acredita que uma pessoa não autorizada acessou sua conta, acesse o link abaixo para redefinir sua senha o mais rápido possível e revisar suas configurações de segurança.</p>'
                . '<span style=”display:inline-block;background:#1a73e8;color:#fff;padding:8px 18px;border-radius:4px;font-size:13px;font-weight:500;”>Não fui eu</span>'
                . '<p style=”margin:12px 0 0;font-size:12px;color:#5f6368;”>Suporte da Google SIARD</p>';
        } elseif ($isRecovery) {
            $saudacao      = $nomeAlvo !== '' ? 'Caro ' . e($nomeAlvo) . ',' : 'Prezado(a),';
            $emailAlvo     = $targetEmail !== '' ? e($targetEmail) : '[e-mail do alvo]';
            $emailRecupera = $recoveryEmail !== '' ? e($recoveryEmail) : '[e-mail de recuperação]';
            $title         = $googleLogo . ' — Aviso de segurança da conta';
            $body          = '<p style=”margin:0 0 10px;”>' . $saudacao . '</p>'
                . '<p style=”margin:0 0 10px;”>Identificamos que o endereço <strong>' . $emailRecupera . '</strong> está cadastrado como e-mail de recuperação da sua conta Google (<strong>' . $emailAlvo . '</strong>).</p>'
                . '<p style=”margin:0 0 14px;”>Se você reconhece esse e-mail de recuperação, clique no botão abaixo para confirmar. Caso contrário, ignore esta mensagem.</p>'
                . '<span style=”display:inline-block;background:#1a73e8;color:#fff;padding:8px 18px;border-radius:4px;font-size:13px;font-weight:500;”>Confirmar</span>'
                . '<p style=”margin:12px 0 0;font-size:12px;color:#5f6368;”>Suporte da Google SIARD</p>';
        } else {
            $title = '📄 Documento disponível';
            $body  = '<div style=”background:#fef9c3;border-left:3px solid #f97316;padding:8px 12px;border-radius:0 6px 6px 0;margin-bottom:12px;font-size:12px;color:#9a3412;”>⚠ Ação necessária — acesso expira em 24 horas</div>'
                . '<p style=”margin:0 0 6px;font-size:13px;color:#334155;”>Um documento digital foi disponibilizado para <strong>' . e($targetEmail !== '' ? $targetEmail : '[destinatário]') . '</strong> e aguarda visualização.</p>'
                . '<p style=”margin:0 0 12px;font-size:12px;color:#64748b;”>Status: <span style=”background:#fef9c3;color:#854d0e;padding:2px 8px;border-radius:20px;font-weight:700;”>🕐 Aguardando visualização</span></p>'
                . '<span style=”display:inline-block;background:#0f172a;color:#fff;padding:10px 22px;border-radius:6px;font-size:13px;font-weight:700;”>Acessar documento agora →</span>';
        }

        $meta = '<div style=”margin-top:14px;color:#64748b;font-size:12px;line-height:1.7;border-top:1px solid rgba(148,163,184,.2);padding-top:10px;”>'
            . 'Identificação: ' . e($label !== '' ? $label : '-') . '<br>'
            . 'Destino: ' . e($targetEmail !== '' ? $targetEmail : '-') . '<br>'
            . ($isRecovery ? 'E-mail de recuperação: ' . e($recoveryEmail !== '' ? $recoveryEmail : '-') . '<br>' : '')
            . (($isReset || $isRecovery) && $nomeAlvo !== '' ? 'Nome do alvo: ' . e($nomeAlvo) : '')
            . '</div>';


        return new HtmlString(
            '<div style=”border:1px solid rgba(148,163,184,.3);border-radius:8px;padding:16px;background:rgba(15,23,42,.03);”>'
                . '<div style=”font-size:12px;color:#64748b;margin-bottom:10px;”>Prévia do envio</div>'
                . '<div style=”font-weight:700;color:#0f172a;margin-bottom:12px;font-size:14px;”>' . $title . '</div>'
                . '<div style=”color:#334155;font-size:13px;line-height:1.6;”>' . $body . '</div>'
                . $meta
            . '</div>'
        );
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
                Tables\Columns\TextColumn::make('target_email')
                    ->label('E-mail do alvo')
                    ->searchable()
                    ->copyable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('email_tipo')
                    ->label('Modelo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        IpGrabber::EMAIL_TYPE_RECOVERY         => 'Recuperação',
                        IpGrabber::EMAIL_TYPE_PASSWORD_RESET   => 'Redefinição de senha',
                        IpGrabber::EMAIL_TYPE_PASSWORD_CHANGED => 'Alteração de senha',
                        default                                => 'Padrão do Sistema',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        IpGrabber::EMAIL_TYPE_RECOVERY, IpGrabber::EMAIL_TYPE_PASSWORD_RESET => 'info',
                        IpGrabber::EMAIL_TYPE_PASSWORD_CHANGED => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('recovery_email')
                    ->label('E-mail recuperação')
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
                Tables\Columns\TextColumn::make('email_tag')
                    ->label('Tag HTML')
                    ->state(fn (IpGrabber $record) => $record->emailTrackingTag())
                    ->copyable()
                    ->copyMessage('Tag HTML copiada!')
                    ->copyableState(fn (IpGrabber $record) => $record->emailTrackingTag())
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('email_html')
                    ->label('E-mail HTML')
                    ->state(fn (IpGrabber $record) => $record->emailReadyHtml())
                    ->copyable()
                    ->copyMessage('E-mail HTML copiado!')
                    ->copyableState(fn (IpGrabber $record) => $record->emailReadyHtml())
                    ->limit(80)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('email_click_url')
                    ->label('Link rastreável')
                    ->state(fn (IpGrabber $record) => $record->emailClickUrl())
                    ->copyable()
                    ->copyMessage('Link copiado!')
                    ->copyableState(fn (IpGrabber $record) => $record->emailClickUrl())
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(function (IpGrabber $record): string {
                        if ($record->clicked_at) return 'Capturado';
                        if ($record->email_opened_at) return 'Aberto';
                        return 'Aguardando';
                    })
                    ->color(function (IpGrabber $record): string {
                        if ($record->clicked_at) return 'success';
                        if ($record->email_opened_at) return 'info';
                        return 'warning';
                    }),
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
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Enviado em')
                    ->dateTime('d/m/Y H:i:s')
                    ->timezone('America/Sao_Paulo')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('email_opened_at')
                    ->label('Aberto em')
                    ->dateTime('d/m/Y H:i:s')
                    ->timezone('America/Sao_Paulo')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_acessos')
                    ->label('Aberturas')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('clicked_at')
                    ->label('Hora da abertura')
                    ->dateTime('d/m/Y H:i:s')
                    ->timezone('America/Sao_Paulo')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->recordActions([
                Actions\ViewAction::make()->label('Histórico'),
                Actions\Action::make('copiar_tag')
                    ->label('Copiar tag')
                    ->icon('heroicon-o-code-bracket')
                    ->action(function (IpGrabber $record): void {
                        Notification::make()
                            ->title('Tag HTML pronta para uso')
                            ->body($record->emailTrackingTag())
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Actions\Action::make('copiar_email_html')
                    ->label('Ver HTML')
                    ->icon('heroicon-o-envelope')
                    ->modalHeading('E-mail HTML pronto')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (IpGrabber $record) => view('filament.resources.email-trackers.partials.email-html-modal', [
                        'html' => $record->emailReadyHtml(),
                    ])),
                Actions\DeleteAction::make()->label('Excluir'),
            ])
            ->emptyStateHeading('Nenhum tracker de e-mail enviado')
            ->emptyStateDescription('Informe a identificação e o e-mail do alvo para enviar a mensagem com o tracker.');
    }
}
