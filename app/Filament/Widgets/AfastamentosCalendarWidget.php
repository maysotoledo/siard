<?php

namespace App\Filament\Widgets;

use App\Enums\StatusAfastamento;
use App\Enums\StatusPeriodoAquisitivo;
use App\Enums\TipoAfastamento;
use App\Models\AfastamentoPeriodoAquisitivo;
use App\Models\AfastamentoSolicitacao;
use App\Models\User;
use App\Services\Afastamentos\AfastamentoService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Saade\FilamentFullCalendar\Actions;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class AfastamentosCalendarWidget extends FullCalendarWidget
{
    public Model|string|null $model = AfastamentoSolicitacao::class;

    protected static ?int $sort = 1;

    public static function getHeading(): string
    {
        return 'Calendário de Afastamentos';
    }

    public function config(): array
    {
        return [
            'firstDay' => 1,
            'locale' => 'pt-br',
            'initialView' => 'dayGridMonth',
            'selectable' => true,
            'selectMirror' => true,
            'unselectAuto' => true,
            'editable' => false,
        ];
    }

    public function dateClick(): string
    {
        return <<<'JS'
            function(info) {
                info.view.calendar.el.__livewire.mountAction('create', {
                    start: info.dateStr,
                    end: info.dateStr
                });
            }
        JS;
    }

    public function select(): string
    {
        return <<<'JS'
            function(info) {
                info.view.calendar.el.__livewire.mountAction('create', {
                    start: info.startStr,
                    end: info.endStr
                });
            }
        JS;
    }

    public function eventClick(): string
    {
        return <<<'JS'
            function(info) {
                info.jsEvent.preventDefault();
                return false;
            }
        JS;
    }

    protected function modalActions(): array
    {
        return [];
    }

    public function getFormSchema(): array
    {
        return [
            Hidden::make('user_id')->default(fn () => auth()->id()),
            Hidden::make('status')->default(StatusAfastamento::SOLICITADO->value),

            Select::make('user_id_select')
                ->label('Servidor')
                ->default(fn () => auth()->id())
                ->required()
                ->disabled(fn () => ! $this->canChooseServidor())
                ->dehydrated(false)
                ->options(function (): array {
                    if ($this->canChooseServidor()) {
                        return User::query()->orderBy('name')->pluck('name', 'id')->all();
                    }

                    $user = auth()->user();

                    return $user ? [$user->id => $user->name] : [];
                })
                ->searchable(fn () => $this->canChooseServidor())
                ->preload(fn () => $this->canChooseServidor())
                ->live()
                ->afterStateUpdated(function (?int $state, Set $set): void {
                    if ($this->canChooseServidor() && $state) {
                        $set('user_id', $state);
                    }

                    $set('periodo_aquisitivo_id', null);
                }),

            Select::make('tipo_afastamento')
                ->label('Tipo de afastamento')
                ->required()
                ->placeholder('')
                ->selectablePlaceholder(false)
                ->options(TipoAfastamento::options())
                ->default(TipoAfastamento::FERIAS->value)
                ->live()
                ->afterStateHydrated(function (?string $state, Set $set): void {
                    if (blank($state)) {
                        $set('tipo_afastamento', TipoAfastamento::FERIAS->value);
                    }
                })
                ->afterStateUpdated(fn (Set $set) => $set('periodo_aquisitivo_id', null)),

            Select::make('periodo_aquisitivo_id')
                ->hidden(fn (Get $get): bool => $this->isAtestado((string) $get('tipo_afastamento')))
                ->label('Período aquisitivo')
                ->options(fn (Get $get): array => $this->periodosOptions((int) $get('user_id'), (string) $get('tipo_afastamento')))
                ->searchable()
                ->preload()
                ->helperText(fn (Get $get): string => $this->deveMostrarAvisoSemPeriodoAquisitivo((int) $get('user_id'), (string) $get('tipo_afastamento'))
                    ? 'O servidor não possui período aquisitivo adquirido com saldo disponível para este tipo de afastamento.'
                    : 'Selecione um período com saldo disponível.'),

            DatePicker::make('data_inicio')
                ->label('Início')
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(fn (?string $state, Set $set, Get $get) => $this->atualizarDias($set, $state, $get('data_fim'))),

            DatePicker::make('data_fim')
                ->label('Fim')
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(fn (?string $state, Set $set, Get $get) => $this->atualizarDias($set, $get('data_inicio'), $state)),

            TextInput::make('dias_solicitados')
                ->label('Dias solicitados')
                ->numeric()
                ->readOnly()
                ->default(0),

            Placeholder::make('preview')
                ->label('')
                ->content(function (Get $get): string {
                    $inicio = $get('data_inicio');
                    $fim = $get('data_fim');
                    $dias = (int) ($get('dias_solicitados') ?? 0);

                    if (! $inicio || ! $fim || $dias < 1) {
                        return 'Selecione o período do afastamento.';
                    }

                    return 'Período: '.Carbon::parse($inicio)->format('d/m/Y').
                        ' até '.Carbon::parse($fim)->format('d/m/Y').
                        " ({$dias} dia(s)).";
                }),

            Textarea::make('justificativa_servidor')
                ->label('Justificativa')
                ->columnSpanFull(),
        ];
    }

    protected function headerActions(): array
    {
        return [
            Actions\CreateAction::make('create')
                ->label('Solicitar afastamento')
                ->mountUsing(function (Schema $form, array $arguments): void {
                    $startStr = $arguments['start'] ?? null;
                    $endStr = $arguments['end'] ?? null;

                    $periodo = self::periodoSelecionadoNoCalendario($startStr, $endStr);

                    $userId = auth()->id();

                    $form->fill([
                        'user_id' => $userId,
                        'user_id_select' => $userId,
                        'tipo_afastamento' => TipoAfastamento::FERIAS->value,
                        'data_inicio' => $periodo['inicio'],
                        'data_fim' => $periodo['fim'],
                        'dias_solicitados' => $periodo['dias'],
                        'status' => StatusAfastamento::SOLICITADO->value,
                    ]);
                })
                ->action(function (array $data): void {
                    try {
                        if (! $this->canChooseServidor()) {
                            $data['user_id'] = auth()->id();
                        }

                        $data['status'] = TipoAfastamento::tryFrom((string) ($data['tipo_afastamento'] ?? '')) === TipoAfastamento::ATESTADO
                            ? StatusAfastamento::APROVADO->value
                            : StatusAfastamento::SOLICITADO->value;

                        app(AfastamentoService::class)->salvar($data);

                        Notification::make()
                            ->title('Solicitação registrada')
                            ->success()
                            ->send();

                        $this->refreshRecords();
                        $this->dispatch('plantaoUpdated');
                        $this->dispatch('$refresh');
                        $this->dispatch('afastamentosUpdated');
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Ação não permitida')
                            ->body(collect($exception->errors())->flatten()->first())
                            ->danger()
                            ->send();

                        throw $exception;
                    }
                }),
        ];
    }

    public function fetchEvents(array $fetchInfo): array
    {
        $rangeStart = Carbon::parse($fetchInfo['start'])->startOfDay();
        $rangeEnd = Carbon::parse($fetchInfo['end'])->endOfDay();

        $query = AfastamentoSolicitacao::query()
            ->with('user')
            ->whereDate('data_inicio', '<=', $rangeEnd->toDateString())
            ->whereDate('data_fim', '>=', $rangeStart->toDateString());

        if (! $this->canViewTodos()) {
            $query->where('user_id', auth()->id());
        }

        return $query->get()
            ->map(function (AfastamentoSolicitacao $solicitacao): array {
                $color = $this->corPorAfastamento($solicitacao);
                $tipo = $solicitacao->tipo_afastamento?->label() ?? 'Afastamento';
                $status = $solicitacao->status?->label() ?? '';
                $title = trim(($solicitacao->user?->name ?? 'Servidor').' - '.$tipo.' - '.$status);

                return [
                    'id' => (string) $solicitacao->id,
                    'title' => $title,
                    'start' => $solicitacao->data_inicio?->toDateString(),
                    'end' => $solicitacao->data_fim?->copy()->addDay()->toDateString(),
                    'allDay' => true,
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                ];
            })
            ->all();
    }

    private function atualizarDias(Set $set, ?string $inicio, ?string $fim): void
    {
        if (! $inicio || ! $fim) {
            $set('dias_solicitados', 0);

            return;
        }

        $start = Carbon::parse($inicio)->startOfDay();
        $end = Carbon::parse($fim)->startOfDay();
        $set('dias_solicitados', $end->lt($start) ? 0 : $start->diffInDays($end) + 1);
    }

    public static function periodoSelecionadoNoCalendario(?string $startStr, ?string $endStr): array
    {
        $inicio = $startStr ? Carbon::parse($startStr)->toDateString() : null;
        $fim = $endStr ? Carbon::parse($endStr)->toDateString() : $inicio;

        if (! $inicio || ! $fim) {
            return ['inicio' => $inicio, 'fim' => $fim, 'dias' => 0];
        }

        $start = Carbon::parse($inicio)->startOfDay();
        $end = Carbon::parse($fim)->startOfDay();

        if ($end->lt($start)) {
            return ['inicio' => $inicio, 'fim' => $inicio, 'dias' => 1];
        }

        return [
            'inicio' => $inicio,
            'fim' => $fim,
            'dias' => (int) $start->diffInDays($end) + 1,
        ];
    }

    private function periodosOptions(int $userId, string $tipo): array
    {
        if ($userId <= 0 || $tipo === '' || $this->isAtestado($tipo)) {
            return [];
        }

        return AfastamentoPeriodoAquisitivo::query()
            ->where('user_id', $userId)
            ->where('tipo_afastamento', $tipo)
            ->where('dias_disponiveis', '>', 0)
            ->whereDate('data_aquisicao', '<=', now()->toDateString())
            ->whereIn('status', [
                StatusPeriodoAquisitivo::ADQUIRIDO->value,
                StatusPeriodoAquisitivo::PARCIALMENTE_USUFRUIDO->value,
                StatusPeriodoAquisitivo::APROVADO->value,
            ])
            ->orderBy('data_aquisicao')
            ->get()
            ->mapWithKeys(fn (AfastamentoPeriodoAquisitivo $periodo): array => [
                $periodo->id => $periodo->data_inicio?->format('d/m/Y').
                    ' - '.$periodo->data_fim?->format('d/m/Y').
                    ' | saldo '.$periodo->dias_disponiveis,
            ])
            ->all();
    }

    private function deveMostrarAvisoSemPeriodoAquisitivo(int $userId, string $tipo): bool
    {
        if ($userId <= 0 || $tipo === '' || $this->isAtestado($tipo)) {
            return false;
        }

        return $this->periodosOptions($userId, $tipo) === [];
    }

    private function isAtestado(?string $tipo): bool
    {
        return TipoAfastamento::tryFrom((string) $tipo) === TipoAfastamento::ATESTADO;
    }

    private function corPorAfastamento(AfastamentoSolicitacao $solicitacao): string
    {
        if ($solicitacao->tipo_afastamento === TipoAfastamento::FERIAS) {
            return match ($solicitacao->status) {
                StatusAfastamento::APROVADO, StatusAfastamento::CONCLUIDO => '#16a34a',
                StatusAfastamento::EM_ANALISE => '#f97316',
                StatusAfastamento::SOLICITADO, StatusAfastamento::RASCUNHO => '#2563eb',
                StatusAfastamento::CANCELADO, StatusAfastamento::INDEFERIDO, StatusAfastamento::INTERROMPIDO => '#dc2626',
                default => '#6b7280',
            };
        }

        if ($solicitacao->tipo_afastamento === TipoAfastamento::LICENCA_PREMIO) {
            return match ($solicitacao->status) {
                StatusAfastamento::APROVADO, StatusAfastamento::CONCLUIDO => '#059669',
                StatusAfastamento::EM_ANALISE => '#f59e0b',
                StatusAfastamento::SOLICITADO, StatusAfastamento::RASCUNHO => '#0ea5e9',
                StatusAfastamento::CANCELADO, StatusAfastamento::INDEFERIDO, StatusAfastamento::INTERROMPIDO => '#b91c1c',
                default => '#6b7280',
            };
        }

        return '#6b7280';
    }

    private function canChooseServidor(): bool
    {
        $user = auth()->user();

        return (bool) $user && (
            $user->hasRole('admin') ||
            $user->hasRole('super_admin') ||
            $user->can('ViewAny:AfastamentoSolicitacao')
        );
    }

    private function canViewTodos(): bool
    {
        $user = auth()->user();

        return (bool) $user && (
            $user->hasRole('admin') ||
            $user->hasRole('super_admin') ||
            $user->hasRole('chefia') ||
            $user->hasRole('dpc') ||
            $user->can('ViewAny:AfastamentoSolicitacao')
        );
    }
}
