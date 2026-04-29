<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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

        $duration = $this->resolveCurrentDurationMinutes();

        $this->form->fill([
            'attendance_slot_duration_minutes' => $duration,
            'attendance_slots' => $this->resolveCurrentSlots($duration),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('attendance_slot_duration_minutes')
                    ->label('Duracao da oitiva')
                    ->options($this->durationOptions())
                    ->default(60)
                    ->required()
                    ->native(false)
                    ->live()
                    ->helperText('Essa duracao sera usada para preencher automaticamente o horario final de cada atendimento.')
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                        $duration = max(15, (int) ($state ?: 60));
                        $slots = $this->recalculateSlots(
                            $get('attendance_slots') ?? [],
                            $duration,
                        );

                        $set('attendance_slots', $slots);
                    }),

                Repeater::make('attendance_slots')
                    ->label('Disponibilidade geral')
                    ->helperText('Clique em + para adicionar um novo horario de atendimento. O horario final e calculado pela duracao da oitiva.')
                    ->defaultItems(0)
                    ->addActionLabel('Adicionar horario')
                    ->addAction(fn (Action $action) => $action->icon(Heroicon::OutlinedPlusCircle))
                    ->deleteAction(fn (Action $action) => $action->icon(Heroicon::OutlinedNoSymbol)->color('gray'))
                    ->reorderable(false)
                    ->collapsible(false)
                    ->itemLabel(fn (array $state): ?string => $this->makeSlotLabel($state))
                    ->schema([
                        TextInput::make('start_time')
                            ->label('Horario inicial')
                            ->type('time')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                $duration = max(15, (int) ($get('../../attendance_slot_duration_minutes') ?: 60));
                                $slots = $get('../../attendance_slots') ?? [];

                                $set('../../attendance_slots', $this->harmonizeSlots(
                                    is_array($slots) ? $slots : [],
                                    $duration,
                                ));
                            }),

                        TextInput::make('end_time')
                            ->label('Horario final')
                            ->type('text')
                            ->readOnly()
                            ->dehydrated(false)
                            ->extraInputAttributes([
                                'readonly' => true,
                                'tabindex' => '-1',
                                'onkeydown' => 'return false;',
                                'onpaste' => 'return false;',
                            ])
                            ->hint('Preenchido automaticamente')
                            ->formatStateUsing(function (?string $state, Get $get): ?string {
                                $duration = max(15, (int) ($get('../../attendance_slot_duration_minutes') ?: 60));

                                return $this->calculateEndTime($get('start_time'), $duration) ?? $state;
                            }),

                        Placeholder::make('slot_preview')
                            ->label('Faixa do atendimento')
                            ->content(function (Get $get): string {
                                $start = (string) ($get('start_time') ?? '');
                                $duration = max(15, (int) ($get('../../attendance_slot_duration_minutes') ?: 60));
                                $end = $this->calculateEndTime($start, $duration);

                                if ($start === '' || ! $end) {
                                    return 'Defina o horario inicial para montar a faixa automaticamente.';
                                }

                                return "{$start} - {$end}";
                            }),
                    ])
                    ->columns(3)
                    ->live()
                    ->afterStateHydrated(function (Get $get, Set $set): void {
                        $duration = max(15, (int) ($get('attendance_slot_duration_minutes') ?: 60));
                        $set('attendance_slots', $this->harmonizeSlots(
                            $get('attendance_slots') ?? [],
                            $duration,
                        ));
                    })
                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                        $duration = max(15, (int) ($get('attendance_slot_duration_minutes') ?: 60));
                        $set('attendance_slots', $this->harmonizeSlots(
                            is_array($state) ? $state : [],
                            $duration,
                        ));
                    }),
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
        $duration = max(15, (int) ($state['attendance_slot_duration_minutes'] ?? 60));
        $hours = $this->normalizeHours($state['attendance_slots'] ?? []);

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
            'attendance_slot_duration_minutes' => $duration,
        ])->save();

        Notification::make()
            ->title('Horarios de atendimento salvos com sucesso.')
            ->success()
            ->send();
    }

    private function resolveCurrentDurationMinutes(): int
    {
        $duration = (int) (auth()->user()?->attendance_slot_duration_minutes ?? 60);

        return $duration > 0 ? $duration : 60;
    }

    private function resolveCurrentSlots(int $duration): array
    {
        $hours = auth()->user()?->attendance_hours;

        if (! is_array($hours) || $hours === []) {
            $hours = ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'];
        }

        return collect($hours)
            ->filter(fn ($hour) => is_string($hour) && preg_match('/^\d{2}:\d{2}$/', $hour))
            ->sort()
            ->values()
            ->map(fn (string $hour): array => [
                'start_time' => $hour,
                'end_time' => $this->calculateEndTime($hour, $duration),
            ])
            ->all();
    }

    private function normalizeHours(array $slots): array
    {
        return collect($slots)
            ->map(fn ($slot) => is_array($slot) ? trim((string) ($slot['start_time'] ?? '')) : '')
            ->filter(fn (string $hour) => preg_match('/^\d{2}:\d{2}$/', $hour))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function recalculateSlots(array $slots, int $duration): array
    {
        $previousEndTime = null;
        $isFirstSlot = true;

        return collect($slots)
            ->map(function ($slot) use ($duration, &$previousEndTime, &$isFirstSlot): array {
                $start = is_array($slot) ? trim((string) ($slot['start_time'] ?? '')) : '';

                if ($isFirstSlot) {
                    $isFirstSlot = false;
                } elseif ($previousEndTime !== null) {
                    $start = $previousEndTime;
                }

                $end = $this->calculateEndTime($start, $duration);
                $previousEndTime = $end;

                return [
                    'start_time' => $start,
                    'end_time' => $end,
                ];
            })
            ->all();
    }

    private function harmonizeSlots(array $slots, int $duration): array
    {
        $previousEndTime = null;

        return collect($slots)
            ->map(function ($slot) use ($duration, &$previousEndTime): array {
                $start = is_array($slot) ? trim((string) ($slot['start_time'] ?? '')) : '';

                if ($start === '' && $previousEndTime !== null) {
                    $start = $previousEndTime;
                }

                $end = $this->calculateEndTime($start, $duration);
                $previousEndTime = $end;

                return [
                    'start_time' => $start,
                    'end_time' => $end,
                ];
            })
            ->all();
    }

    private function calculateEndTime(?string $startTime, int $durationMinutes): ?string
    {
        $startTime = trim((string) $startTime);

        if (! preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            return null;
        }

        return Carbon::createFromFormat('H:i', $startTime)
            ->addMinutes($durationMinutes)
            ->format('H:i');
    }

    private function normalizeTimeValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{2}:\d{2}$/', $value) ? $value : null;
    }

    private function makeSlotLabel(array $state): ?string
    {
        $start = trim((string) ($state['start_time'] ?? ''));
        $end = trim((string) ($state['end_time'] ?? ''));

        if ($start === '') {
            return 'Novo horario';
        }

        return $end !== '' ? "{$start} - {$end}" : $start;
    }

    private function durationOptions(): array
    {
        return [
            30 => '30 minutos',
            45 => '45 minutos',
            60 => '1 hora',
            90 => '1 hora e 30 minutos',
            120 => '2 horas',
        ];
    }
}
