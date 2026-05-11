<?php

namespace App\Filament\Resources\PixelAdmin\Widgets;

use App\Models\PixelModuleSetting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

class SistemaAtualizacaoWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.widgets.sistema-atualizacao-widget';

    protected int|string|array $columnSpan = 'full';

    public ?array $data = [];

    public function mount(): void
    {
        $setting = PixelModuleSetting::current();

        $this->form->fill([
            'manutencao_ativa'    => $setting->manutencao_ativa,
            'manutencao_prevista' => $setting->manutencao_prevista?->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i:s'),
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->components([
                Forms\Components\Toggle::make('manutencao_ativa')
                    ->label('Ativar aviso de atualização do sistema')
                    ->helperText('Quando ativado, exibe um banner de aviso na página inicial.')
                    ->live(),

                Forms\Components\DateTimePicker::make('manutencao_prevista')
                    ->label('Data e hora prevista da atualização')
                    ->timezone('America/Sao_Paulo')
                    ->displayFormat('d/m/Y H:i')
                    ->seconds(false)
                    ->nullable()
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => (bool) $get('manutencao_ativa')),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        PixelModuleSetting::current()->update([
            'manutencao_ativa'    => $data['manutencao_ativa'],
            'manutencao_prevista' => $data['manutencao_ativa'] ? ($data['manutencao_prevista'] ?? null) : null,
        ]);

        Notification::make()
            ->title('Configuração de atualização salva!')
            ->success()
            ->send();
    }
}
