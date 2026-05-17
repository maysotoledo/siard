@php
    $tipoLabels = [
        'relatorio_completo' => 'Relatório Completo',
        'resumo_tecnico'     => 'Resumo Técnico',
        'linha_investigacao' => 'Linha de Investigação',
        'conclusao'          => 'Conclusão',
        'minuta_autoridade'  => 'Minuta para Autoridade',
    ];
@endphp

@if (! $report)
    <div class="p-4 text-gray-400">Relatório não encontrado.</div>
@else
    <div class="space-y-4 p-1">
        {{-- Header info --}}
        <div class="flex flex-wrap gap-2 text-xs text-gray-500">
            <span class="font-medium">{{ $tipoLabels[$report->tipo] ?? $report->tipo }}</span>
            @if($report->provider)
                <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-800 rounded-full">{{ $report->provider }}</span>
            @endif
            @if($report->model)
                <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-800 rounded-full font-mono">{{ $report->model }}</span>
            @endif
            <span>{{ $report->created_at->format('d/m/Y H:i') }}</span>
        </div>

        {{-- Copy button --}}
        <div class="flex justify-end">
            <x-filament::button
                size="sm"
                color="gray"
                icon="heroicon-o-clipboard-document"
                x-data
                x-on:click="navigator.clipboard.writeText({{ json_encode($report->resposta) }}).then(() => $tooltip('Copiado!'))"
            >
                Copiar texto
            </x-filament::button>
        </div>

        {{-- Report body --}}
        <div
            class="bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-5 text-sm font-mono whitespace-pre-wrap leading-relaxed max-h-[65vh] overflow-y-auto"
        >{{ $report->resposta ?? 'Sem conteúdo.' }}</div>
    </div>
@endif
