<?php

namespace App\Filament\Resources\PixelTracks;

use App\Filament\Resources\PixelTracks\Pages\ListProcessedIpGrabbers;
use App\Filament\Resources\PixelTracks\Pages\ViewProcessedIpGrabber;
use App\Models\IpGrabber;
use App\Models\IpGrabberFoto;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

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
        return parent::getEloquentQuery()
            ->with('criador')
            ->withCount('fotos')
            ->where('tracking_channel', 'link')
            ->latest();
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
                    ->state(fn (IpGrabber $record) => $record->clicked_at ? 'Capturado' : 'Aguardando')
                    ->color(fn (IpGrabber $record) => $record->clicked_at ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('total_acessos')->label('Acessos')->alignCenter()->sortable(),
                Tables\Columns\TextColumn::make('clicked_at')->label('1º Acesso')->dateTime('d/m/Y H:i:s')->timezone('America/Sao_Paulo')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('ip')->label('IP Público')->copyable()->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('porta')->label('Porta')->alignCenter()->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->timezone('America/Sao_Paulo')->sortable()->toggleable(isToggledHiddenByDefault: true),
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
                        $foto = $record->fotos()->first();

                        if (! $foto) {
                            return new HtmlString(
                                '<div class="flex flex-col items-center gap-2 py-8 text-gray-400">'
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
                Actions\DeleteAction::make()->label('Excluir')->visible(fn (IpGrabber $record) => static::canDelete($record)),
            ])
            ->emptyStateHeading('Nenhum IP Grabber encontrado')
            ->emptyStateIcon('heroicon-o-shield-exclamation');
    }
}
