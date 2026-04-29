<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DefinirHorarioAtendimento extends Page implements HasSchemas
{
    use HasPageShield;
    use InteractsWithSchemas;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;
    protected static ?string $navigationLabel = 'Definir Horario de Atendimento';
    protected static ?string $title = 'Definir Horario de Atendimento';
    protected static ?string $slug = 'definir-horario-atendimento';
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.definir-horario-atendimento';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user?->hasAnyRole(['epc', 'cartorio_central', 'super_admin']);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'attendance_hours_text' => implode(PHP_EOL, $this->resolveCurrentHours()),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('attendance_hours_text')
                    ->label('Horarios de atendimento')
                    ->rows(10)
                    ->placeholder("08:00\n09:00\n10:00\n11:00\n14:00\n15:00\n16:00\n17:00")
                    ->helperText('Informe um horario por linha no formato HH:MM. Exemplo: 08:00, 09:15, 14:00.')
                    ->required(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('salvar')
                ->label('Salvar horarios')
                ->icon(Heroicon::OutlinedCheck)
                ->action(fn () => $this->save()),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $hours = $this->normalizeHours((string) ($state['attendance_hours_text'] ?? ''));

        if ($hours === []) {
            Notification::make()
                ->title('Selecione pelo menos um horario.')
                ->warning()
                ->send();

            return;
        }

        $user = auth()->user();
        $user?->forceFill([
            'attendance_hours' => $hours,
        ])->save();

        Notification::make()
            ->title('Horarios de atendimento salvos com sucesso.')
            ->success()
            ->send();
    }

    private function resolveCurrentHours(): array
    {
        $hours = auth()->user()?->attendance_hours;

        if (! is_array($hours) || $hours === []) {
            return ['08:00', '14:00', '09:00', '15:00', '10:00', '16:00', '11:00', '17:00'];
        }

        return collect($hours)
            ->filter(fn ($hour) => is_string($hour) && preg_match('/^\d{2}:\d{2}$/', $hour))
            ->values()
            ->all();
    }

    private function normalizeHours(string $input): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $input) ?: [];

        $hours = collect($lines)
            ->map(fn ($line) => trim((string) $line))
            ->filter(fn (string $line) => $line !== '')
            ->filter(fn (string $line) => preg_match('/^\d{2}:\d{2}$/', $line))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $matutinos = array_values(array_filter($hours, fn (string $hour) => (int) substr($hour, 0, 2) < 12));
        $vespertinos = array_values(array_filter($hours, fn (string $hour) => (int) substr($hour, 0, 2) >= 12));

        $intercalados = [];
        $max = max(count($matutinos), count($vespertinos));

        for ($i = 0; $i < $max; $i++) {
            if (isset($matutinos[$i])) {
                $intercalados[] = $matutinos[$i];
            }

            if (isset($vespertinos[$i])) {
                $intercalados[] = $vespertinos[$i];
            }
        }

        return $intercalados;
    }
}
