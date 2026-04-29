<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Agendas\AgendaResource;
use App\Models\Bloqueio;
use App\Models\Evento;
use App\Models\User;
use App\Services\EventoService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\Carbon;
use Filament\Actions\Action as FilamentAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\GridDirection;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Saade\FilamentFullCalendar\Actions;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class CalendarWidget extends FullCalendarWidget
{
    use HasWidgetShield;

    public Model|string|null $model = Evento::class;

    protected string $view = 'filament.widgets.calendar-widget';

    protected static ?int $sort = 2;

    public ?int $agendaUserId = null;

    public ?string $calendarSyncSignature = null;

    /**
     * ✅ Controla se o modal atual é "somente mensagem".
     * (sem horário / fim de semana / bloqueio / sem permissão)
     * Usado para esconder o Submit e mostrar o botão "Fechar".
     */
    public bool $modalSemHorario = false;

    protected function isOwnAgendaOnlyUser(?User $user): bool
    {
        return (bool) $user?->hasRole('epc');
    }

    protected function agendaSelectableUsersQuery()
    {
        return User::query()
            ->where(function ($query) {
                $query->role('epc')
                    ->orWhere(fn ($roleQuery) => $roleQuery->role('cartorio_central'));
            });
    }

    public function mount(): void
    {
        $user = auth()->user();

        if ($this->isOwnAgendaOnlyUser($user)) {
            $this->agendaUserId = (int) $user->getKey();
            session(['agenda_user_id' => $this->agendaUserId]);
            $this->rememberCalendarSyncSignature();
            return;
        }

        if (! $this->agendaSelectableUsersQuery()->exists()) {
            $this->agendaUserId = null;
            session()->forget('agenda_user_id');
            $this->calendarSyncSignature = null;
            return;
        }

        $sessionUserId = session('agenda_user_id');

        $validSessionUserId = $this->agendaSelectableUsersQuery()
            ->whereKey($sessionUserId)
            ->value('id');

        if ($validSessionUserId) {
            $this->agendaUserId = (int) $validSessionUserId;
            $this->rememberCalendarSyncSignature();
            return;
        }

        if ($user?->hasRole('cartorio_central')) {
            $this->agendaUserId = (int) $user->getKey();
        } else {
            $this->agendaUserId = (int) $this->agendaSelectableUsersQuery()
                ->orderBy('name')
                ->value('id');
        }

        if ($this->agendaUserId) {
            session(['agenda_user_id' => $this->agendaUserId]);
            $this->rememberCalendarSyncSignature();
        } else {
            session()->forget('agenda_user_id');
            $this->calendarSyncSignature = null;
        }
    }

    #[On('agendaUserSelected')]
    public function setAgendaUser(int $userId): void
    {
        if ($this->isOwnAgendaOnlyUser(auth()->user())) {
            return;
        }

        $isSelectableAgendaUser = $this->agendaSelectableUsersQuery()->whereKey($userId)->exists();
        if (! $isSelectableAgendaUser) {
            return;
        }

        $this->agendaUserId = $userId;
        session(['agenda_user_id' => $userId]);

        $this->rememberCalendarSyncSignature();
        $this->forceCalendarRefresh();
    }

    public function forceCalendarRefresh(): void
    {
        $this->rememberCalendarSyncSignature();
        $this->refreshRecords();
        $this->dispatch('$refresh');
    }

    public function pollCalendarForChanges(): void
    {
        if (! $this->agendaUserId) {
            $this->calendarSyncSignature = null;
            return;
        }

        $signature = $this->makeCalendarSyncSignature();

        if ($this->calendarSyncSignature === null) {
            $this->calendarSyncSignature = $signature;
            return;
        }

        if ($signature === $this->calendarSyncSignature) {
            return;
        }

        $this->calendarSyncSignature = $signature;
        $this->refreshRecords();
    }

    private function rememberCalendarSyncSignature(): void
    {
        $this->calendarSyncSignature = $this->agendaUserId
            ? $this->makeCalendarSyncSignature()
            : null;
    }

    private function makeCalendarSyncSignature(): string
    {
        if (! $this->agendaUserId) {
            return 'none';
        }

        $eventStats = Evento::withTrashed()
            ->where('user_id', $this->agendaUserId)
            ->selectRaw('COUNT(*) as total, MAX(updated_at) as last_updated_at, MAX(deleted_at) as last_deleted_at')
            ->first();

        $blockStats = Bloqueio::query()
            ->where('user_id', $this->agendaUserId)
            ->selectRaw('COUNT(*) as total, MAX(updated_at) as last_updated_at')
            ->first();

        $agendaUser = User::query()
            ->select(['id', 'attendance_hours', 'updated_at'])
            ->find($this->agendaUserId);

        return implode('|', [
            $this->agendaUserId,
            (int) ($eventStats?->total ?? 0),
            (string) ($eventStats?->last_updated_at ?? ''),
            (string) ($eventStats?->last_deleted_at ?? ''),
            (int) ($blockStats?->total ?? 0),
            (string) ($blockStats?->last_updated_at ?? ''),
            md5(json_encode($agendaUser?->attendance_hours ?? [])),
            (string) ($agendaUser?->updated_at ?? ''),
        ]);
    }

    public static function getHeading(): string
    {
        return 'Calendário';
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user) return false;

        if ($user->hasRole('epc') || $user->hasRole('cartorio_central')) return true;

        return User::query()
            ->where(function ($query) {
                $query->role('epc')
                    ->orWhere(fn ($roleQuery) => $roleQuery->role('cartorio_central'));
            })
            ->exists();
    }

    public function config(): array
    {
        return [
            'selectable' => true,
            'unselectAuto' => true,
            'firstDay' => 1,
            'editable' => true,
            'locale' => 'pt-br',

            'displayEventTime' => false,

            'slotLabelFormat' => [
                'hour' => '2-digit',
                'minute' => '2-digit',
                'hour12' => false,
            ],
            'eventTimeFormat' => [
                'hour' => '2-digit',
                'minute' => '2-digit',
                'hour12' => false,
            ],

            // ✅ Arrastar: abre o edit.
            'eventDrop' => RawJs::make(<<<'JS'
function(info) {
    const lw = info?.view?.calendar?.el?.__livewire;
    if (!lw) return;

    if (typeof info.revert === 'function') info.revert();

    const eventId = info.event?.id;
    const startStr =
        info.event?.startStr
        || (info.event?.start ? info.event.start.toISOString() : null);

    lw.mountAction('edit', {
        event: {
            id: eventId,
            start: startStr,
            end: info.event?.endStr || null,
        }
    });
}
JS),
        ];
    }

    public function eventDidMount(): string
    {
        return <<<'JS'
function({ event, el }) {
    if (event.display === 'background') return;

    const content =
        event?.extendedProps?.procedimento
        ?? event?.title
        ?? '';

    if (!content) return;

    el.setAttribute('x-tooltip', 'tooltip');
    el.setAttribute('x-data', '{ tooltip: ' + JSON.stringify(content) + ' }');
    el.setAttribute('title', content);

    if (window.Alpine && typeof window.Alpine.initTree === 'function') {
        window.Alpine.initTree(el);
    }
}
JS;
    }

    public function dateClick(): string
    {
        return <<<JS
function(info) {
    const lw = info?.view?.calendar?.el?.__livewire;
    if (!lw) return;

    lw.mountAction('create', {
        start: info.dateStr,
        end: info.dateStr
    });
}
JS;
    }

    private function isDiaUtil(string $dia): bool
    {
        return ! Carbon::parse($dia)->isWeekend();
    }

    private function getBloqueioDoDia(string $dia): ?Bloqueio
    {
        if (! $this->agendaUserId) return null;

        return Bloqueio::query()
            ->where('user_id', $this->agendaUserId)
            ->whereDate('dia', $dia)
            ->first();
    }

    private function isDiaBloqueado(string $dia): bool
    {
        return (bool) $this->getBloqueioDoDia($dia);
    }

    private function isDiaBloqueadoOuFimDeSemana(string $dia): bool
    {
        return (! $this->isDiaUtil($dia)) || $this->isDiaBloqueado($dia);
    }

    private function baseHourOptions(): array
    {
        $hours = $this->resolveAgendaAttendanceHours();
        $options = [];

        foreach ($hours as $h) {
            if (is_int($h)) {
                $options[sprintf('%02d:00', $h)] = sprintf('%02d:00', $h);
            } elseif (is_string($h) && preg_match('/^\d{2}:\d{2}$/', $h)) {
                $options[$h] = $h;
            }
        }

        return $options;
    }

    private function resolveAgendaAttendanceHours(): array
    {
        $default = ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'];

        if (! $this->agendaUserId) {
            return $default;
        }

        $user = User::query()
            ->select(['id', 'attendance_hours'])
            ->find($this->agendaUserId);

        $hours = is_array($user?->attendance_hours) ? $user->attendance_hours : [];

        $valid = collect($hours)
            ->filter(fn ($hour) => is_string($hour) && preg_match('/^\d{2}:\d{2}$/', $hour))
            ->sort()
            ->values()
            ->all();

        return $valid !== [] ? $valid : $default;
    }

    private function availableHourOptions(?string $dia, ?int $ignoreEventoId = null): array
    {
        if (! $dia) return $this->baseHourOptions();
        if (! $this->agendaUserId) return [];

        if ($this->isDiaBloqueadoOuFimDeSemana($dia)) {
            return [];
        }

        return array_diff_key(
            $this->baseHourOptions(),
            array_flip($this->occupiedHourValues($dia, $ignoreEventoId))
        );
    }

    private function displayHourOptions(?string $dia, ?int $ignoreEventoId = null): array
    {
        return $this->formatHourOptionsForTwoColumns(
            $this->availableHourOptions($dia, $ignoreEventoId),
        );
    }

    private function formatHourOptionsForTwoColumns(array $options): array
    {
        $morning = [];
        $afternoon = [];

        foreach ($options as $value => $label) {
            $hour = (int) substr((string) $value, 0, 2);

            if ($hour < 12) {
                $morning[$value] = $label;
            } else {
                $afternoon[$value] = $label;
            }
        }

        $morning = array_values(array_map(
            fn ($value, $label) => ['value' => $value, 'label' => $label],
            array_keys($morning),
            $morning,
        ));

        $afternoon = array_values(array_map(
            fn ($value, $label) => ['value' => $value, 'label' => $label],
            array_keys($afternoon),
            $afternoon,
        ));

        $maxRows = max(count($morning), count($afternoon));
        $formatted = [];

        for ($i = 0; $i < $maxRows; $i++) {
            if (isset($morning[$i])) {
                $formatted[$morning[$i]['value']] = $morning[$i]['label'];
            } elseif (isset($afternoon[$i])) {
                $formatted["__placeholder_morning_{$i}"] = ' ';
            }

            if (isset($afternoon[$i])) {
                $formatted[$afternoon[$i]['value']] = $afternoon[$i]['label'];
            } elseif (isset($morning[$i])) {
                $formatted["__placeholder_afternoon_{$i}"] = ' ';
            }
        }

        return $formatted;
    }

    private function occupiedHourValues(?string $dia, ?int $ignoreEventoId = null): array
    {
        if (! $dia || ! $this->agendaUserId || $this->isDiaBloqueadoOuFimDeSemana($dia)) {
            return [];
        }

        return Evento::query()
            ->where('user_id', $this->agendaUserId)
            ->whereDate('starts_at', $dia)
            ->when($ignoreEventoId, fn ($q) => $q->whereKeyNot($ignoreEventoId))
            ->pluck('starts_at')
            ->map(fn ($dt) => Carbon::parse($dt)->format('H:i'))
            ->unique()
            ->values()
            ->all();
    }

    private function assertDiaAgendavelOrThrow(string $dia): void
    {
        if (! $this->isDiaUtil($dia)) {
            throw ValidationException::withMessages([
                'hora_inicio' => 'Agendamentos apenas em dias úteis (segunda a sexta).',
            ]);
        }

        if ($this->isDiaBloqueado($dia)) {
            throw ValidationException::withMessages([
                'hora_inicio' => 'Este dia está bloqueado para este EPC.',
            ]);
        }
    }

    private function makeSemHorarioMessage(string $dia): string
    {
        $data = Carbon::parse($dia)->format('d/m/Y');
        return "❌ Não há horários disponíveis em {$data} para este EPC.";
    }

    private function makeFimDeSemanaMessage(string $dia): string
    {
        $data = Carbon::parse($dia)->format('d/m/Y');
        return "❌ {$data} é fim de semana.\nAgendamentos somente em dias úteis (segunda a sexta).";
    }

    private function makeBloqueioMessage(string $dia): string
    {
        $data = Carbon::parse($dia)->format('d/m/Y');
        $bloqueio = $this->getBloqueioDoDia($dia);
        $motivo = $bloqueio?->motivo ?: 'Sem motivo informado.';
        return "🚫 Dia bloqueado para este EPC ({$data}).\nMotivo: {$motivo}";
    }

    public function getFormSchema(): array
    {
        return [
            Hidden::make('evento_id'),
            Hidden::make('dia')->dehydrated(false),

            Hidden::make('somente_msg')->dehydrated(false),
            Hidden::make('somente_msg_texto')->dehydrated(false),

            Hidden::make('sem_horario')->dehydrated(false),
            Hidden::make('sem_horario_msg')->dehydrated(false),

            Placeholder::make('msg_somente')
                ->label('⚠️ Aviso!')
                ->visible(fn (Get $get): bool => (bool) $get('somente_msg'))
                ->content(fn (Get $get): string => (string) ($get('somente_msg_texto') ?: '')),

            TextInput::make('intimado')
                ->label('Intimado')
                ->maxLength(255)
                ->required(fn (Get $get) => ! $get('somente_msg'))
                ->visible(fn (Get $get) => ! $get('somente_msg')),

            TextInput::make('numero_procedimento')
                ->label('Número do procedimento')
                ->maxLength(80)
                ->required(fn (Get $get) => ! $get('somente_msg'))
                ->visible(fn (Get $get) => ! $get('somente_msg')),

            // ✅ NOVO: WhatsApp
            TextInput::make('whatsapp')
                ->label('WhatsApp')
                ->tel()
                ->maxLength(30)
                ->placeholder('(99) 99999-9999')
                ->visible(fn (Get $get) => ! $get('somente_msg')),

            // ✅ NOVO: Presencial (verde) x Online (azul)
            ToggleButtons::make('oitiva_online')
                ->label('Modalidade da oitiva')
                ->boolean(
                    trueLabel: 'Online',
                    falseLabel: 'Presencial',
                )
                ->inline()
                ->colors([
                    0 => 'success', // Presencial
                    1 => 'info',    // Online
                ])
                ->extraAttributes(['class' => 'agenda-modalidade-toggle'])
                ->default(false) // ✅ Presencial selecionado por padrão
                ->visible(fn (Get $get) => ! $get('somente_msg')),

            Fieldset::make('Horário')
                ->visible(fn (Get $get) => ! $get('somente_msg'))
                ->extraAttributes(['class' => 'agenda-horario-card'])
                ->schema([
                    ToggleButtons::make('hora_inicio')
                        ->hiddenLabel()
                        ->options(fn (Get $get) => $this->displayHourOptions($get('dia'), $get('evento_id')))
                        ->columns(2)
                        ->gridDirection(GridDirection::Row)
                        ->disableOptionWhen(fn (string $value): bool => str_starts_with($value, '__placeholder_'))
                        ->disabled(fn (Get $get) => (bool) $get('somente_msg'))
                        ->required(fn (Get $get) => ! $get('somente_msg'))
                        ->extraAttributes(['class' => 'agenda-horario-toggle'])
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set, Get $get) {
                            if ($get('somente_msg')) return;

                            $dia = $get('dia');
                            if (! $dia || ! $state || str_starts_with($state, '__placeholder_')) return;

                            $inicio = Carbon::parse("{$dia} {$state}");
                            $fim = $inicio->copy()->addHour();

                            $set('starts_at', $inicio->toDateTimeString());
                            $set('ends_at', $fim->toDateTimeString());
                        }),
                ]),

            Hidden::make('starts_at')
                ->required(fn (Get $get) => ! $get('somente_msg')),

            Hidden::make('ends_at')
                ->required(fn (Get $get) => ! $get('somente_msg')),
        ];
    }

    protected function headerActions(): array
    {
        return [
            FilamentAction::make('selecionarUsuario')
                ->label('Selecionar usuário')
                ->icon('heroicon-o-user')
                ->visible(fn () => ! auth()->user()?->hasRole('epc') && ! $this->agendaUserId)
                ->url(fn () => AgendaResource::getUrl('index')),

            Actions\CreateAction::make()
                ->label('Agendar')
                ->createAnother(false)
                ->modalSubmitAction(fn (\Filament\Actions\Action $action) => $action->visible(fn (): bool => ! $this->modalSemHorario))
                ->modalCancelAction(function (\Filament\Actions\Action $action) {
                    return $action->label($this->modalSemHorario ? 'Fechar' : 'Cancelar');
                })
                ->modalCloseButton(true)

                ->mountUsing(function (Schema $form, array $arguments) {
                    $this->modalSemHorario = false;

                    if (! $this->agendaUserId) {
                        Notification::make()
                            ->title('Sem EPC selecionado')
                            ->body('Selecione um EPC para visualizar/agendar.')
                            ->warning()
                            ->send();

                        $this->modalSemHorario = true;

                        $form->fill([
                            'evento_id' => null,
                            'dia' => null,

                            'somente_msg' => true,
                            'somente_msg_texto' => '⚠️ Selecione um EPC para visualizar/agendar.',

                            'sem_horario' => false,
                            'sem_horario_msg' => null,

                            'intimado' => null,
                            'numero_procedimento' => null,
                            'whatsapp' => null,
                            'oitiva_online' => false, // ✅ Presencial default

                            'hora_inicio' => null,
                            'starts_at' => null,
                            'ends_at' => null,
                        ]);
                        return;
                    }

                    $dia = isset($arguments['start'])
                        ? Carbon::parse($arguments['start'])->toDateString()
                        : null;

                    if (! $dia) {
                        $this->modalSemHorario = true;

                        $form->fill([
                            'evento_id' => null,
                            'dia' => null,

                            'somente_msg' => true,
                            'somente_msg_texto' => '⚠️ Clique em um dia no calendário para agendar.',

                            'sem_horario' => false,
                            'sem_horario_msg' => null,

                            'intimado' => null,
                            'numero_procedimento' => null,
                            'whatsapp' => null,
                            'oitiva_online' => false,

                            'hora_inicio' => null,
                            'starts_at' => null,
                            'ends_at' => null,
                        ]);
                        return;
                    }

                    if (Carbon::parse($dia)->isWeekend()) {
                        $this->modalSemHorario = true;

                        $form->fill([
                            'evento_id' => null,
                            'dia' => $dia,

                            'somente_msg' => true,
                            'somente_msg_texto' => $this->makeFimDeSemanaMessage($dia),

                            'sem_horario' => false,
                            'sem_horario_msg' => null,

                            'intimado' => null,
                            'numero_procedimento' => null,
                            'whatsapp' => null,
                            'oitiva_online' => false,

                            'hora_inicio' => null,
                            'starts_at' => null,
                            'ends_at' => null,
                        ]);
                        return;
                    }

                    if ($this->isDiaBloqueado($dia)) {
                        $this->modalSemHorario = true;

                        $form->fill([
                            'evento_id' => null,
                            'dia' => $dia,

                            'somente_msg' => true,
                            'somente_msg_texto' => $this->makeBloqueioMessage($dia),

                            'sem_horario' => false,
                            'sem_horario_msg' => null,

                            'intimado' => null,
                            'numero_procedimento' => null,
                            'whatsapp' => null,
                            'oitiva_online' => false,

                            'hora_inicio' => null,
                            'starts_at' => null,
                            'ends_at' => null,
                        ]);
                        return;
                    }

                    $options = $this->availableHourOptions($dia);

                    if (empty($options)) {
                        $this->modalSemHorario = true;

                        $form->fill([
                            'evento_id' => null,
                            'dia' => $dia,

                            'somente_msg' => true,
                            'somente_msg_texto' => $this->makeSemHorarioMessage($dia),

                            'sem_horario' => true,
                            'sem_horario_msg' => $this->makeSemHorarioMessage($dia),

                            'intimado' => null,
                            'numero_procedimento' => null,
                            'whatsapp' => null,
                            'oitiva_online' => false,

                            'hora_inicio' => null,
                            'starts_at' => null,
                            'ends_at' => null,
                        ]);
                        return;
                    }

                    $hora = array_key_first($options);
                    $inicio = Carbon::parse("{$dia} {$hora}");
                    $fim = $inicio->copy()->addHour();

                    $form->fill([
                        'evento_id' => null,
                        'dia' => $dia,

                        'somente_msg' => false,
                        'somente_msg_texto' => null,

                        'sem_horario' => false,
                        'sem_horario_msg' => null,

                        'hora_inicio' => $hora,
                        'starts_at' => $inicio->toDateTimeString(),
                        'ends_at' => $fim->toDateTimeString(),

                        'intimado' => null,
                        'numero_procedimento' => null,
                        'whatsapp' => null,
                        'oitiva_online' => false,
                    ]);
                })
                ->mutateFormDataUsing(function (array $data): array {
                    if ($this->modalSemHorario || ($data['somente_msg'] ?? false)) {
                        throw ValidationException::withMessages([
                            'hora_inicio' => 'Não é possível concluir esta ação.',
                        ]);
                    }

                    if (! $this->agendaUserId) {
                        throw ValidationException::withMessages([
                            'intimado' => 'Selecione um EPC para agendar.',
                        ]);
                    }

                    if (empty($data['starts_at'])) {
                        throw ValidationException::withMessages([
                            'hora_inicio' => 'Não é possível agendar neste dia.',
                        ]);
                    }

                    $start = Carbon::parse($data['starts_at']);
                    $dia = $start->toDateString();

                    $this->assertDiaAgendavelOrThrow($dia);

                    $jaExiste = Evento::query()
                        ->where('user_id', $this->agendaUserId)
                        ->whereDate('starts_at', $dia)
                        ->whereTime('starts_at', $start->format('H:i:s'))
                        ->exists();

                    if ($jaExiste) {
                        throw ValidationException::withMessages([
                            'hora_inicio' => 'Este horário já foi agendado para este usuário. Selecione outro.',
                        ]);
                    }

                    $data['user_id'] = $this->agendaUserId;
                    $data['created_by'] = auth()->id();

                    // ✅ garante boolean (Presencial default)
                    $data['oitiva_online'] = (bool) ($data['oitiva_online'] ?? false);

                    unset(
                        $data['dia'],
                        $data['hora_inicio'],
                        $data['evento_id'],
                        $data['sem_horario'],
                        $data['sem_horario_msg'],
                        $data['somente_msg'],
                        $data['somente_msg_texto'],
                    );

                    return $data;
                })
                ->using(function (array $data) {
                    return app(EventoService::class)->criar($data);
                })
                ->after(function (): void {
                    $this->forceCalendarRefresh();
                }),
        ];
    }

    protected function modalActions(): array
    {
        return [
            Actions\EditAction::make()
                ->modalSubmitAction(fn (\Filament\Actions\Action $action) => $action->visible(fn (): bool => ! $this->modalSemHorario))
                ->modalCancelAction(function (\Filament\Actions\Action $action) {
                    return $action->label($this->modalSemHorario ? 'Fechar' : 'Cancelar');
                })
                ->modalCloseButton(true)

                ->mountUsing(function (Model $record, Schema $form, array $arguments) {
                    /** @var \App\Models\Evento $record */
                    $this->modalSemHorario = false;

                    if (! Gate::allows('update', $record)) {
                        $this->modalSemHorario = true;

                        $dia = $record->starts_at ? Carbon::parse($record->starts_at)->toDateString() : null;

                        $form->fill([
                            'evento_id' => $record->id,
                            'dia' => $dia,

                            'somente_msg' => true,
                            'somente_msg_texto' => '⚠️ Você não tem permissão para editar este agendamento.',

                            'sem_horario' => false,
                            'sem_horario_msg' => null,

                            'hora_inicio' => null,
                            'starts_at' => null,
                            'ends_at' => null,

                            'intimado' => $record->intimado,
                            'numero_procedimento' => $record->numero_procedimento,
                            'whatsapp' => $record->whatsapp,
                            'oitiva_online' => (bool) $record->oitiva_online,
                        ]);

                        $this->forceCalendarRefresh();
                        return;
                    }

                    $startArg = $arguments['event']['start'] ?? $record->starts_at;
                    $targetStart = Carbon::parse($startArg);
                    $targetDia = $targetStart->toDateString();

                    if (Carbon::parse($targetDia)->isWeekend()) {
                        $this->modalSemHorario = true;

                        $form->fill([
                            'evento_id' => $record->id,
                            'dia' => $targetDia,

                            'somente_msg' => true,
                            'somente_msg_texto' => $this->makeFimDeSemanaMessage($targetDia),

                            'sem_horario' => false,
                            'sem_horario_msg' => null,

                            'hora_inicio' => null,
                            'starts_at' => null,
                            'ends_at' => null,

                            'intimado' => $record->intimado,
                            'numero_procedimento' => $record->numero_procedimento,
                            'whatsapp' => $record->whatsapp,
                            'oitiva_online' => (bool) $record->oitiva_online,
                        ]);

                        $this->forceCalendarRefresh();
                        return;
                    }

                    if ($this->isDiaBloqueado($targetDia)) {
                        $this->modalSemHorario = true;

                        $form->fill([
                            'evento_id' => $record->id,
                            'dia' => $targetDia,

                            'somente_msg' => true,
                            'somente_msg_texto' => $this->makeBloqueioMessage($targetDia),

                            'sem_horario' => false,
                            'sem_horario_msg' => null,

                            'hora_inicio' => null,
                            'starts_at' => null,
                            'ends_at' => null,

                            'intimado' => $record->intimado,
                            'numero_procedimento' => $record->numero_procedimento,
                            'whatsapp' => $record->whatsapp,
                            'oitiva_online' => (bool) $record->oitiva_online,
                        ]);

                        $this->forceCalendarRefresh();
                        return;
                    }

                    $options = $this->availableHourOptions($targetDia, (int) $record->id);

                    if (empty($options)) {
                        $this->modalSemHorario = true;

                        $form->fill([
                            'evento_id' => $record->id,
                            'dia' => $targetDia,

                            'somente_msg' => true,
                            'somente_msg_texto' => $this->makeSemHorarioMessage($targetDia),

                            'sem_horario' => true,
                            'sem_horario_msg' => $this->makeSemHorarioMessage($targetDia),

                            'hora_inicio' => null,
                            'starts_at' => null,
                            'ends_at' => null,

                            'intimado' => $record->intimado,
                            'numero_procedimento' => $record->numero_procedimento,
                            'whatsapp' => $record->whatsapp,
                            'oitiva_online' => (bool) $record->oitiva_online,
                        ]);

                        $this->forceCalendarRefresh();
                        return;
                    }

                    $originalDia = Carbon::parse($record->starts_at)->toDateString();

                    if ($targetDia !== $originalDia) {
                        $hora = array_key_first($options);
                    } else {
                        $candidate = $targetStart->format('H:00');
                        $hora = isset($options[$candidate]) ? $candidate : array_key_first($options);
                    }

                    $inicio = Carbon::parse("{$targetDia} {$hora}");
                    $fim = $inicio->copy()->addHour();

                    $form->fill([
                        'evento_id' => $record->id,
                        'dia' => $targetDia,

                        'somente_msg' => false,
                        'somente_msg_texto' => null,

                        'sem_horario' => false,
                        'sem_horario_msg' => null,

                        'hora_inicio' => $hora,
                        'starts_at' => $inicio->toDateTimeString(),
                        'ends_at' => $fim->toDateTimeString(),

                        'intimado' => $record->intimado,
                        'numero_procedimento' => $record->numero_procedimento,
                        'whatsapp' => $record->whatsapp,
                        'oitiva_online' => (bool) $record->oitiva_online,
                    ]);
                })
                ->mutateFormDataUsing(function (array $data): array {
                    if ($this->modalSemHorario || ($data['somente_msg'] ?? false)) {
                        throw ValidationException::withMessages([
                            'hora_inicio' => 'Não é possível concluir esta ação.',
                        ]);
                    }

                    if (! $this->agendaUserId) {
                        throw ValidationException::withMessages([
                            'hora_inicio' => 'Selecione um EPC para editar este agendamento.',
                        ]);
                    }

                    if (empty($data['starts_at'])) {
                        throw ValidationException::withMessages([
                            'hora_inicio' => 'Não há horários disponíveis para este dia.',
                        ]);
                    }

                    $start = Carbon::parse($data['starts_at']);
                    $dia = $start->toDateString();

                    $this->assertDiaAgendavelOrThrow($dia);

                    $eventoId = (int) ($data['evento_id'] ?? 0);

                    $jaExiste = Evento::query()
                        ->where('user_id', $this->agendaUserId)
                        ->whereDate('starts_at', $dia)
                        ->whereTime('starts_at', $start->format('H:i:s'))
                        ->when($eventoId, fn ($q) => $q->whereKeyNot($eventoId))
                        ->exists();

                    if ($jaExiste) {
                        throw ValidationException::withMessages([
                            'hora_inicio' => 'Este horário já foi agendado para este usuário. Selecione outro.',
                        ]);
                    }

                    $data['user_id'] = $this->agendaUserId;

                    // ✅ garante boolean
                    $data['oitiva_online'] = (bool) ($data['oitiva_online'] ?? false);

                    unset(
                        $data['dia'],
                        $data['hora_inicio'],
                        $data['evento_id'],
                        $data['sem_horario'],
                        $data['sem_horario_msg'],
                        $data['somente_msg'],
                        $data['somente_msg_texto'],
                    );

                    return $data;
                })
                ->using(function (Model $record, array $data) {
                    /** @var \App\Models\Evento $record */
                    Gate::authorize('update', $record);
                    return app(EventoService::class)->editar($record, $data);
                })
                ->after(function (): void {
                    $this->forceCalendarRefresh();
                }),

            Actions\DeleteAction::make()
                ->label('Cancelar')
                ->visible(fn (Model $record): bool => Gate::allows('delete', $record))
                ->modalHeading('Cancelar agendamento?')
                ->modalDescription('O cancelamento preserva o histórico e pode ser restaurado depois.')
                ->action(function (Model $record): void {
                    /** @var \App\Models\Evento $record */
                    Gate::authorize('delete', $record);
                    app(EventoService::class)->cancelar($record);
                })
                ->after(function (): void {
                    $this->forceCalendarRefresh();
                }),
        ];
    }

    private function getBlockedDaysInRange(Carbon $start, Carbon $end): Collection
    {
        if (! $this->agendaUserId) return collect();

        return Bloqueio::query()
            ->where('user_id', $this->agendaUserId)
            ->whereDate('dia', '>=', $start->toDateString())
            ->whereDate('dia', '<=', $end->toDateString())
            ->pluck('dia');
    }

    public function fetchEvents(array $fetchInfo): array
    {
        if (! $this->agendaUserId) return [];

        $rangeStart = Carbon::parse($fetchInfo['start'])->startOfDay();
        $rangeEnd = Carbon::parse($fetchInfo['end'])->endOfDay();

        $eventos = Evento::query()
            ->where('user_id', $this->agendaUserId)
            ->where('starts_at', '<', $rangeEnd)
            ->where(function ($q) use ($rangeStart) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', $rangeStart);
            })
            ->get();

        $diasComAgendamento = $eventos
            ->map(fn (Evento $e) => Carbon::parse($e->starts_at)->toDateString())
            ->unique()
            ->values();

        $agendamentos = $eventos
            ->map(function (Evento $e) {
                $intimado = $e->intimado ?: 'Agendamento';
                $proc = $e->numero_procedimento ?: null;

                $hora = $e->starts_at ? Carbon::parse($e->starts_at)->format('G') : '--';
                $tipo = $e->oitiva_online ? 'Online' : 'Presencial';

                // ✅ Ex.: "14h Fulano 2025.123 (Presencial)"
                $title = "{$hora}h {$intimado}" . ($proc ? " {$proc}" : '') . " ({$tipo})";

                // ✅ Tooltip (multi-linha)
                $tooltip = array_filter([
                    $proc ? "Procedimento: {$proc}" : null,
                    $e->whatsapp ? "WhatsApp: {$e->whatsapp}" : null,
                    "Modalidade: {$tipo}",
                ]);

                return [
                    'id' => (string) $e->id,
                    'title' => $title,
                    'start' => $e->starts_at,
                    'end' => $e->ends_at,
                    'procedimento' => implode("\n", $tooltip),
                ];
            })
            ->all();

        $blockedDays = $this->getBlockedDaysInRange($rangeStart, $rangeEnd)
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values();

        $background = [];

        $cursor = $rangeStart->copy();
        while ($cursor->lte($rangeEnd)) {
            $day = $cursor->toDateString();

            if ($cursor->isWeekend()) {
                $background[] = [
                    'id' => 'weekend-' . $day,
                    'start' => $day,
                    'end' => $cursor->copy()->addDay()->toDateString(),
                    'allDay' => true,
                    'display' => 'background',
                    'backgroundColor' => 'rgba(239, 81, 81, 1)',
                    'borderColor' => 'rgba(255, 0, 0, 0.35)',
                ];

                $cursor->addDay();
                continue;
            }

            if ($blockedDays->contains($day)) {
                $background[] = [
                    'id' => 'blocked-' . $day,
                    'start' => $day,
                    'end' => $cursor->copy()->addDay()->toDateString(),
                    'allDay' => true,
                    'display' => 'background',
                    'backgroundColor' => 'rgba(239, 81, 81, 1)',
                    'borderColor' => 'rgba(255, 0, 0, 0.35)',
                ];

                $cursor->addDay();
                continue;
            }

            if ($diasComAgendamento->contains($day)) {
                $background[] = [
                    'id' => 'busy-' . $day,
                    'start' => $day,
                    'end' => $cursor->copy()->addDay()->toDateString(),
                    'allDay' => true,
                    'display' => 'background',
                    'backgroundColor' => 'rgba(0, 128, 0, 1)',
                    'borderColor' => 'rgba(0, 128, 0, 0.28)',
                ];
            }

            $cursor->addDay();
        }

        return array_merge($agendamentos, $background);
    }
}
