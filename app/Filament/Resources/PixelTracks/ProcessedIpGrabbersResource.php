<?php

namespace App\Filament\Resources\PixelTracks;

use App\Filament\Resources\PixelTracks\Pages\ListProcessedIpGrabbers;
use App\Filament\Resources\PixelTracks\Pages\ViewProcessedIpGrabber;
use App\Models\IpGrabber;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProcessedIpGrabbersResource extends Resource
{
    protected static ?string $model = IpGrabber::class;

    protected static ?string $slug = 'processed-ip-grabbers';

    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-shield-check'; }
    public static function getNavigationGroup(): string|\UnitEnum|null { return 'Administração do Sistema'; }
    public static function getNavigationLabel(): string { return 'IP Grabber Processados'; }
    public static function getNavigationSort(): ?int { return 61; }
    public static function getModelLabel(): string { return 'IP Grabber Processado'; }
    public static function getPluralModelLabel(): string { return 'IP Grabber Processados'; }

    public static function getPages(): array
    {
        return [
            'index' => ListProcessedIpGrabbers::route('/'),
            'view' => ViewProcessedIpGrabber::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user !== null && ($user->hasRole('super_admin') || $user->can('ViewAny:TodosPixelTrack'));
    }

    public static function canView(Model $record): bool { return static::canViewAny(); }
    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        return $user !== null && ($user->hasRole('super_admin') || $user->can('Delete:PixelTrack'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('criador')->where('tracking_channel', 'link')->latest();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('criador.name')->label('Criado por')->searchable()->sortable()->badge()->color('info'),
                Tables\Columns\TextColumn::make('label')->label('Identificação')->searchable()->sortable()->wrap(),
                Tables\Columns\TextColumn::make('preview_tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'noticia' => 'Notícia',
                        'pix_bradesco' => 'PIX Bradesco',
                        default => 'Mensagem',
                    }),
                Tables\Columns\TextColumn::make('pixel_url')
                    ->label('URL do link')
                    ->state(fn (IpGrabber $record) => $record->trackingUrl())
                    ->copyable()
                    ->copyMessage('URL copiada!')
                    ->copyableState(fn (IpGrabber $record) => $record->trackingUrl())
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (IpGrabber $record) => $record->clicked_at ? 'Capturado' : 'Aguardando'),
                Tables\Columns\TextColumn::make('total_acessos')->label('Acessos')->alignCenter()->sortable(),
                Tables\Columns\TextColumn::make('clicked_at')->label('1º Acesso')->dateTime('d/m/Y H:i:s')->timezone('America/Sao_Paulo')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('ip')->label('IP Público')->copyable()->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('porta')->label('Porta')->alignCenter()->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->timezone('America/Sao_Paulo')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Actions\ViewAction::make()->label('Histórico')->icon('heroicon-o-clock'),
                Actions\Action::make('copiar_img_tag')
                    ->label('Tag <img>')
                    ->icon('heroicon-o-code-bracket')
                    ->color('gray')
                    ->action(function (IpGrabber $record): void {
                        Notification::make()
                            ->title('Tag HTML do link (e-mail)')
                            ->body($record->emailTrackingTag())
                            ->info()
                            ->persistent()
                            ->send();
                    }),
                Actions\DeleteAction::make()->label('Excluir')->visible(fn (IpGrabber $record) => static::canDelete($record)),
            ])
            ->emptyStateHeading('Nenhum IP Grabber encontrado')
            ->emptyStateIcon('heroicon-o-shield-exclamation');
    }
}
