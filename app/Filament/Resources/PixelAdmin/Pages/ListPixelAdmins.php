<?php

namespace App\Filament\Resources\PixelAdmin\Pages;

use App\Filament\Resources\PixelAdmin\PixelAdminResource;
use App\Models\PixelModuleSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPixelAdmins extends ListRecords
{
    protected static string $resource = PixelAdminResource::class;

    protected function getHeaderActions(): array
    {
        $setting = PixelModuleSetting::current();

        return [
            Action::make('payment_settings')
                ->label('Configurar cobrança')
                ->icon('heroicon-o-cog-6-tooth')
                ->fillForm([
                    'payment_enabled' => $setting->payment_enabled,
                ])
                ->schema([
                    Toggle::make('payment_enabled')
                        ->label('Habilitar pagamento no Pixel Tracker')
                        ->helperText('Se desabilitado, o sistema não cobra mensalidade e todo usuário com permissão do Shield terá acesso. O super_admin sempre tem acesso livre.'),
                ])
                ->action(function (array $data): void {
                    $setting = PixelModuleSetting::current();
                    $setting->payment_enabled = (bool) ($data['payment_enabled'] ?? false);
                    $setting->save();

                    Notification::make()
                        ->title('Configuração atualizada')
                        ->body($setting->payment_enabled
                            ? 'A cobrança do Pixel Tracker foi habilitada.'
                            : 'A cobrança do Pixel Tracker foi desabilitada.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
