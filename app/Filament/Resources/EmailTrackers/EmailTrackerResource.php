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
                                    IpGrabber::EMAIL_TYPE_MARKETING => 'E-mail marketing',
                                    IpGrabber::EMAIL_TYPE_RECOVERY => 'E-mail de recuperação',
                                ])
                                ->descriptions([
                                    IpGrabber::EMAIL_TYPE_MARKETING => 'Mensagem neutra com botão para visualização do comprovante.',
                                    IpGrabber::EMAIL_TYPE_RECOVERY => 'Aviso de segurança do SIARD sobre alteração de senha, sem uso de marca de terceiros.',
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
                                ->helperText('Informe o e-mail de recuperação localizado na análise. O template usa apenas a identidade SIARD, sem marca de terceiros nem coleta credenciais.')
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
        $type = (string) ($get('email_tipo') ?: IpGrabber::EMAIL_TYPE_MARKETING);
        $targetEmail = trim((string) $get('target_email'));
        $recoveryEmail = trim((string) $get('recovery_email'));
        $label = trim((string) $get('label'));

        $title = $type === IpGrabber::EMAIL_TYPE_RECOVERY
            ? 'Alerta de segurança: alteração de senha'
            : 'Comprovante disponível';
        $body = $type === IpGrabber::EMAIL_TYPE_RECOVERY
            ? 'Aviso de segurança do SIARD informando alteração de senha, com ação “Não fui eu”.'
            : 'Mensagem de marketing/aviso com botão para visualização do comprovante.';

        return new HtmlString(
            '<div style="border:1px solid rgba(148,163,184,.3);border-radius:8px;padding:16px;background:rgba(15,23,42,.03);">'
                . '<div style="font-size:12px;color:#64748b;margin-bottom:8px;">Prévia do envio</div>'
                . '<div style="font-weight:700;color:#0f172a;margin-bottom:6px;">' . e($title) . '</div>'
                . '<div style="color:#334155;font-size:13px;line-height:1.5;">' . e($body) . '</div>'
                . '<div style="margin-top:12px;color:#64748b;font-size:12px;line-height:1.6;">'
                    . 'Identificação: ' . e($label !== '' ? $label : '-') . '<br>'
                    . 'Destino: ' . e($targetEmail !== '' ? $targetEmail : '-') . '<br>'
                    . ($type === IpGrabber::EMAIL_TYPE_RECOVERY ? 'E-mail de recuperação: ' . e($recoveryEmail !== '' ? $recoveryEmail : '-') : '')
                . '</div>'
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
                    ->formatStateUsing(fn (?string $state): string => $state === IpGrabber::EMAIL_TYPE_RECOVERY ? 'Recuperação' : 'Marketing')
                    ->color(fn (?string $state): string => $state === IpGrabber::EMAIL_TYPE_RECOVERY ? 'info' : 'gray'),
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
                    ->state(fn (IpGrabber $record) => $record->clicked_at ? 'Aberto' : 'Aguardando')
                    ->color(fn (IpGrabber $record) => $record->clicked_at ? 'success' : 'warning'),
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
