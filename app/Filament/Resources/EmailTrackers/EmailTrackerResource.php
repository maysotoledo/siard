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
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
        return 'Investigação Telemática';
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
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Envio com Tracker')
                ->description('Informe a identificação e o e-mail do alvo para disparar a mensagem com o pixel de rastreamento.')
                ->components([
                    Forms\Components\TextInput::make('label')
                        ->label('Identificação')
                        ->placeholder('Ex: Campanha maio - suspeito João')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('target_email')
                        ->label('E-mail do alvo')
                        ->placeholder('alvo@exemplo.com')
                        ->email()
                        ->required()
                        ->maxLength(255)
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
                Tables\Columns\TextColumn::make('target_email')
                    ->label('E-mail do alvo')
                    ->searchable()
                    ->copyable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('email_tag')
                    ->label('Tag HTML')
                    ->state(fn (IpGrabber $record) => $record->emailTrackingTag())
                    ->copyable()
                    ->copyMessage('Tag HTML copiada!')
                    ->copyableState(fn (IpGrabber $record) => $record->emailTrackingTag())
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
                Actions\DeleteAction::make()->label('Excluir'),
            ])
            ->emptyStateHeading('Nenhum tracker de e-mail enviado')
            ->emptyStateDescription('Informe a identificação e o e-mail do alvo para enviar a mensagem com o tracker.');
    }
}
