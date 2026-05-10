<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Http;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;


class IpLookup extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    use HasPageShield;

    // ✅ Filament 4 tipa como BackedEnum|string|null
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Consulta IP/Domínio';
    protected static ?string $title = 'Consulta IP/Domínio';
    protected static ?string $slug = 'ip-lookup';

    protected string $view = 'filament.pages.ip-lookup';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Investigação Telemática';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }


    public ?array $data = [];
    public ?array $result = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('query')
                    ->label('IP ou domínio')
                    ->placeholder('8.8.8.8 ou exemplo.com')
                    ->required()
                    ->helperText('Aceita IPv4/IPv6 ou domínio.')
                    ->maxLength(255),
            ])
            ->statePath('data');
    }

    public function lookup(): void
    {
        // Se quiser garantir validação antes, você pode trocar por:
        // $this->form->validate();
        $state = $this->form->getState();
        $query = trim($state['query'] ?? '');

        $this->result = null;

        try {
            $response = Http::timeout(10)->get(
                "http://ip-api.com/json/{$query}",
                [
                    // reduz payload pedindo só os campos necessários
                    'fields' => 'status,message,query,city,isp,org,mobile',
                ]
            );

            if (! $response->ok()) {
                Notification::make()
                    ->title('Falha ao consultar ip-api')
                    ->body('Resposta HTTP: ' . $response->status())
                    ->danger()
                    ->send();

                return;
            }

            $json = $response->json();

            if (($json['status'] ?? null) !== 'success') {
                Notification::make()
                    ->title('Consulta inválida')
                    ->body($json['message'] ?? 'Não foi possível obter dados.')
                    ->warning()
                    ->send();

                return;
            }

            $empresa = trim(($json['isp'] ?? ''));
            $tipoConexao = ($json['mobile'] ?? false) ? 'Móvel' : 'Residencial';

            $this->result = [
                'query' => $json['query'] ?? $query,
                'city' => $json['city'] ?? '-',
                'company' => $empresa !== '' ? $empresa : '-',
                'connection_type' => $tipoConexao,
            ];
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erro ao consultar ip-api')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
