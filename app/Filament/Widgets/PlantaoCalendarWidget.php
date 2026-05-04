<?php

namespace App\Filament\Widgets;

use App\Enums\PlantaoStatus;
use App\Models\PlantaoEquipe;
use App\Models\PlantaoEscala;
use App\Services\Plantao\PlantaoCalendarService;
use App\Services\Plantao\PlantaoCqhService;
use App\Services\Plantao\PlantaoPermutaService;
use Carbon\Carbon;
use Filament\Actions\Action as FilamentAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Saade\FilamentFullCalendar\Actions;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class PlantaoCalendarWidget extends FullCalendarWidget
{
    public Model|string|null $model = PlantaoEscala::class;

    public function config(): array
    {
        return [
            'locale' => 'pt-br',
            'initialView' => 'dayGridMonth',
            'firstDay' => 1,
            'editable' => false,
            'selectable' => false,
            'dayMaxEvents' => false,
            'eventDisplay' => 'block',
        ];
    }

    public function eventContent(): string
    {
        return <<<JS
            function(arg) {
                const props = arg.event.extendedProps || {};
                const wrapper = document.createElement('div');
                wrapper.style.display = 'grid';
                wrapper.style.gap = '2px';
                wrapper.style.padding = '2px 3px';
                wrapper.style.whiteSpace = 'normal';
                wrapper.style.lineHeight = '1.15';
                wrapper.style.fontSize = '11px';
                wrapper.style.fontWeight = '700';

                const getValue = function(value) {
                    if (value && typeof value === 'object') {
                        return value;
                    }

                    return {
                        original: value || '-',
                        atual: value || '-',
                        permutado: false,
                    };
                };

                const addLine = function(label, value, color) {
                    const data = getValue(value);
                    if (data.permutado) {
                        const originalLine = document.createElement('div');
                        originalLine.textContent = label + ': ' + (data.original || '-');
                        originalLine.style.color = color;
                        originalLine.style.textDecoration = 'line-through';
                        originalLine.style.opacity = '0.72';
                        originalLine.style.overflow = 'hidden';
                        originalLine.style.textOverflow = 'ellipsis';
                        wrapper.appendChild(originalLine);

                        const atualLine = document.createElement('div');
                        atualLine.textContent = label + ': ' + (data.atual || '-');
                        atualLine.style.color = color;
                        atualLine.style.overflow = 'hidden';
                        atualLine.style.textOverflow = 'ellipsis';
                        wrapper.appendChild(atualLine);
                    } else {
                        const line = document.createElement('div');
                        line.textContent = label + ': ' + (data.atual || data.original || '-');
                        line.style.color = color;
                        line.style.overflow = 'hidden';
                        line.style.textOverflow = 'ellipsis';
                        wrapper.appendChild(line);
                    }
                };

                (props.ipc || []).slice(0, 2).forEach(function(item) {
                    addLine('IPC', item, '#16a34a');
                });

                while (wrapper.children.length < 2) {
                    addLine('IPC', '-', '#16a34a');
                }

                addLine('EPC', props.epc, '#2563eb');
                if (props.dpc) {
                    addLine('DPC', props.dpc, '#7c3aed');
                }
                if (props.dpcContato) {
                    addLine('Contato', props.dpcContato, '#6b7280');
                }
                addLine('CQH', props.cqh, '#f97316');

                return { domNodes: [wrapper] };
            }
        JS;
    }

    public static function getHeading(): string
    {
        return 'Escala de Plantão';
    }

    public function fetchEvents(array $fetchInfo): array
    {
        return app(PlantaoCalendarService::class)->eventos(
            Carbon::parse($fetchInfo['start'])->startOfDay(),
            Carbon::parse($fetchInfo['end'])->endOfDay(),
        );
    }

    public function getFormSchema(): array
    {
        return [
            Placeholder::make('detalhes')
                ->label('Detalhes do dia')
                ->content(function (): string {
                    $record = $this->getRecord();

                    if (! $record instanceof PlantaoEscala) {
                        return '-';
                    }

                    return $record->data_plantao?->format('d/m/Y').' | 07h às 07h';
                }),

            Placeholder::make('plantonistas')
                ->label('Equipe atual')
                ->content(function (): string {
                    $record = $this->getRecord();

                    if (! $record instanceof PlantaoEscala) {
                        return '-';
                    }

                    return app(PlantaoCalendarService::class)->titulo($record);
                })
                ->columnSpanFull(),

            Select::make('equipe_id')
                ->label('Equipe de plantão')
                ->options(fn (): array => PlantaoEquipe::query()->where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->all())
                ->searchable()
                ->preload()
                ->disabled(fn (): bool => ! $this->canPlantao('update_plantao')),

            Select::make('cqh_pessoa')
                ->label('CQH Geral')
                ->options(fn (): array => app(PlantaoCqhService::class)->cqhOptions())
                ->searchable()
                ->preload()
                ->disabled(fn (): bool => ! $this->canPlantao('manage_cqh')),

            Select::make('status')
                ->options(PlantaoStatus::options())
                ->disabled(fn (): bool => ! $this->canPlantao('update_plantao')),

            Textarea::make('observacao')
                ->columnSpanFull()
                ->disabled(fn (): bool => ! $this->canPlantao('update_plantao')),
        ];
    }

    protected function headerActions(): array
    {
        return [];
    }

    protected function modalActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Editar dia')
                ->visible(fn (): bool => $this->canPlantao('update_plantao') || $this->canPlantao('manage_cqh'))
                ->after(function (): void {
                    Notification::make()->title('Escala atualizada')->success()->send();
                    $this->refreshRecords();
                }),

            FilamentAction::make('permutar')
                ->label('Permutar')
                ->icon('heroicon-o-arrows-right-left')
                ->modalSubmitActionLabel('Permutar')
                ->visible(fn (): bool => $this->canPlantao('permutar_plantao') || $this->canPlantao('permutar_cqh'))
                ->schema([
                    Select::make('tipo_funcao')
                        ->options(['ipc_plantao' => 'IPC', 'epc_plantao' => 'EPC', 'cqh_geral' => 'CQH Geral'])
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('servidor_original_id', null);
                            $set('servidor_substituto_id', null);
                        })
                        ->required(),
                    Select::make('servidor_original_id')
                        ->label('Servidor original')
                        ->options(fn (Get $get): array => $this->servidoresOriginaisDoDia($get('tipo_funcao')))
                        ->helperText('Lista somente os servidores escalados no dia selecionado.')
                        ->searchable()
                        ->required(),
                    Select::make('escala_destino_id')
                        ->label('Dia de destino')
                        ->options(fn (): array => $this->diasDestinoOptions())
                        ->live()
                        ->afterStateUpdated(fn (Set $set): mixed => $set('servidor_substituto_id', null))
                        ->searchable()
                        ->required(),
                    Select::make('servidor_substituto_id')
                        ->label('Servidor destino')
                        ->options(fn (Get $get): array => $this->servidoresDoDia((int) ($get('escala_destino_id') ?? 0), $get('tipo_funcao')))
                        ->helperText('Lista somente os servidores escalados no dia de destino.')
                        ->searchable()
                        ->required(),
                    Textarea::make('motivo'),
                ])
                ->action(function (array $data): void {
                    $record = $this->getRecord();

                    if (! $record instanceof PlantaoEscala) {
                        return;
                    }

                    try {
                        app(PlantaoPermutaService::class)->permutarEntreDias(
                            $record->id,
                            (int) $data['escala_destino_id'],
                            $data['servidor_original_id'],
                            $data['servidor_substituto_id'],
                            (string) $data['tipo_funcao'],
                            $data['motivo'] ?? null,
                        );

                        $this->record = $record->refresh();
                        $this->refreshRecords();
                        $this->dispatch('filament-fullcalendar--refresh');
                        $this->dispatch('$refresh');

                        Notification::make()->title('Permuta registrada')->success()->send();
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Permuta não permitida')
                            ->body(collect($exception->errors())->flatten()->first())
                            ->danger()
                            ->send();

                        throw $exception;
                    }
                }),
        ];
    }

    private function canPlantao(string $permission): bool
    {
        $user = Auth::user();

        return (bool) ($user && ($user->can($permission) || $user->hasAnyRole(['admin', 'super_admin'])));
    }

    private function diasDestinoOptions(): array
    {
        $record = $this->getRecord();

        return PlantaoEscala::query()
            ->whereKeyNot($record instanceof PlantaoEscala ? $record->id : 0)
            ->orderBy('data_plantao')
            ->get()
            ->mapWithKeys(fn (PlantaoEscala $escala): array => [
                $escala->id => $escala->data_plantao?->format('d/m/Y').' - '.$escala->data_plantao?->translatedFormat('l'),
            ])
            ->all();
    }

    private function servidoresOriginaisDoDia(?string $tipoFuncao = null): array
    {
        $record = $this->getRecord();

        if (! $record instanceof PlantaoEscala) {
            return [];
        }

        return $this->servidoresDaEscala($record, $tipoFuncao);
    }

    private function servidoresDoDia(int $escalaId, ?string $tipoFuncao = null): array
    {
        if ($escalaId <= 0) {
            return [];
        }

        $escala = PlantaoEscala::query()->with(['equipe.servidores.user', 'cqhGeral', 'permutas.servidorOriginal', 'permutas.servidorSubstituto'])->find($escalaId);

        if (! $escala instanceof PlantaoEscala) {
            return [];
        }

        return $this->servidoresDaEscala($escala, $tipoFuncao);
    }

    private function servidoresDaEscala(PlantaoEscala $escala, ?string $tipoFuncao = null): array
    {
        $membros = app(PlantaoCalendarService::class)->membrosFinais($escala);
        $options = [];

        if ($tipoFuncao === null || $tipoFuncao === '' || $tipoFuncao === 'ipc_plantao') {
            foreach ($membros['ipc'] as $pessoa) {
                if ($pessoa instanceof Model) {
                    $options[app(PlantaoCqhService::class)->keyFor($pessoa)] = 'IPC: '.app(PlantaoCqhService::class)->nomePessoa($pessoa);
                }
            }
        }

        if ($tipoFuncao === null || $tipoFuncao === '' || $tipoFuncao === 'epc_plantao') {
            foreach ($membros['epc'] as $pessoa) {
                if ($pessoa instanceof Model) {
                    $options[app(PlantaoCqhService::class)->keyFor($pessoa)] = 'EPC: '.app(PlantaoCqhService::class)->nomePessoa($pessoa);
                }
            }
        }

        if (($tipoFuncao === null || $tipoFuncao === '' || $tipoFuncao === 'cqh_geral') && $escala->cqhGeral) {
            $options[app(PlantaoCqhService::class)->keyFor($escala->cqhGeral)] = 'CQH: '.app(PlantaoCqhService::class)->nomePessoa($escala->cqhGeral);
        }

        return $options;
    }
}
