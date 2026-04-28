<?php

namespace App\Filament\Pages;

use App\Models\Evento;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

class AgendaPesquisaIntimados extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';
    protected static ?string $navigationLabel = 'Pesquisa de Intimados';
    protected static ?string $title = 'Pesquisa de Intimados';
    protected static ?string $slug = 'agenda-pesquisa-intimados';

    protected string $view = 'filament.pages.agenda-pesquisa-intimados';

    public ?array $data = [];
    public array $results = [];
    public ?string $searchedTerm = null;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Agenda';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('query')
                    ->label('Nome do intimado')
                    ->placeholder('Digite o nome ou parte do nome')
                    ->required()
                    ->maxLength(160),
            ])
            ->statePath('data');
    }

    public function search(): void
    {
        $state = $this->form->getState();
        $query = trim((string) ($state['query'] ?? ''));

        $this->results = [];
        $this->searchedTerm = $query;

        if ($query === '') {
            Notification::make()
                ->title('Informe o nome do intimado')
                ->warning()
                ->send();

            return;
        }

        $events = Evento::query()
            ->with('user:id,name')
            ->whereNotNull('intimado')
            ->where('intimado', 'like', '%' . $query . '%')
            ->orderBy('starts_at')
            ->get();

        $this->results = $events->map(function (Evento $evento): array {
            $startsAt = $evento->starts_at;
            $endsAt = $evento->ends_at;

            return [
                'intimado' => (string) $evento->intimado,
                'dia' => $startsAt?->format('d/m/Y') ?? '-',
                'horario' => $startsAt
                    ? $startsAt->format('H:i') . ($endsAt ? ' - ' . $endsAt->format('H:i') : '')
                    : '-',
                'procedimento' => trim((string) ($evento->numero_procedimento ?? '')) ?: '-',
                'escrivao' => trim((string) ($evento->user?->name ?? '')) ?: '-',
            ];
        })->all();

        if (count($this->results) === 0) {
            Notification::make()
                ->title('Nenhum agendamento encontrado')
                ->body('Nao foi localizado nenhum intimado com esse nome.')
                ->warning()
                ->send();
        }
    }
}
