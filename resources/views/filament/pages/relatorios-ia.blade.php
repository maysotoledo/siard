<x-filament-panels::page>
    @php $reports = $this->reports; @endphp

    @if ($reports->isEmpty())
        <x-filament::section>
            <div class="text-center py-12 text-gray-400">
                <x-filament::icon icon="heroicon-o-cpu-chip" class="mx-auto h-12 w-12 mb-3 opacity-30" />
                <div class="text-sm">Nenhum relatório gerado ainda.</div>
                <div class="text-xs mt-1">Acesse <strong>Contextos da investigação</strong>, abra um registro e clique em um dos botões de geração de IA.</div>
            </div>
        </x-filament::section>
    @else
        <div class="space-y-4">
            @foreach($reports as $report)
                @php
                    $tipoLabels = [
                        'relatorio_completo' => 'Relatório Completo',
                        'resumo_tecnico'     => 'Resumo Técnico',
                        'linha_investigacao' => 'Linha de Investigação',
                        'conclusao'          => 'Conclusão',
                        'minuta_autoridade'  => 'Minuta para Autoridade',
                    ];

                    $statusColors = [
                        'pending'    => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
                        'processing' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                        'done'       => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                        'failed'     => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                    ];

                    $statusLabel = ['pending' => 'Pendente', 'processing' => 'Processando', 'done' => 'Concluído', 'failed' => 'Falhou'][$report->status] ?? $report->status;
                    $tipoLabel   = $tipoLabels[$report->tipo] ?? $report->tipo;
                @endphp

                <x-filament::section>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <div class="font-semibold text-sm">{{ $tipoLabel }}</div>
                            <div class="flex flex-wrap gap-2 text-xs text-gray-500">
                                <span class="px-2 py-0.5 rounded-full font-medium {{ $statusColors[$report->status] ?? '' }}">
                                    {{ $statusLabel }}
                                </span>
                                @if($report->provider)
                                    <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-800 rounded-full">{{ $report->provider }}</span>
                                @endif
                                @if($report->model)
                                    <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-800 rounded-full font-mono">{{ $report->model }}</span>
                                @endif
                                <span>{{ $report->created_at->format('d/m/Y H:i') }}</span>
                                @if($report->investigationContext?->numero_bo)
                                    <span>BO {{ $report->investigationContext->numero_bo }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @if($report->isDone() && $report->resposta)
                                <x-filament::button
                                    size="sm"
                                    color="primary"
                                    icon="heroicon-o-eye"
                                    wire:click="verRelatorio({{ $report->id }})"
                                >
                                    Ver
                                </x-filament::button>

                                <x-filament::button
                                    size="sm"
                                    color="gray"
                                    icon="heroicon-o-clipboard-document"
                                    x-data
                                    x-on:click="navigator.clipboard.writeText({{ json_encode($report->resposta) }}).then(() => $tooltip('Copiado!'))"
                                >
                                    Copiar
                                </x-filament::button>
                            @endif

                            @if($report->isFailed())
                                <div class="text-xs text-red-500 max-w-xs truncate" title="{{ $report->erro }}">
                                    ⚠ {{ $report->erro }}
                                </div>
                            @endif

                            <x-filament::button
                                size="sm"
                                color="warning"
                                icon="heroicon-o-arrow-path"
                                wire:click="regenerarRelatorio({{ $report->id }})"
                                wire:confirm="Regerar este relatório?"
                            >
                                Regerar
                            </x-filament::button>

                            <x-filament::button
                                size="sm"
                                color="danger"
                                icon="heroicon-o-trash"
                                wire:click="excluirRelatorio({{ $report->id }})"
                                wire:confirm="Excluir este relatório?"
                            >
                                Excluir
                            </x-filament::button>
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
