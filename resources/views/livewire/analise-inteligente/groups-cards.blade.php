<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="text-sm text-gray-600 dark:text-gray-300">
            Mostrando <span class="font-semibold">{{ count($pagedRows) }}</span> de
            <span class="font-semibold">{{ number_format($total, 0, ',', '.') }}</span> grupos
            (página <span class="font-semibold">{{ $page }}</span> de <span class="font-semibold">{{ $lastPage }}</span>)
        </div>

        <div class="flex items-center gap-2">
            <x-filament::button size="sm" color="gray" wire:click="prevPage" :disabled="$page <= 1">
                Anterior
            </x-filament::button>

            <x-filament::button size="sm" color="gray" wire:click="nextPage" :disabled="$page >= $lastPage">
                Próxima
            </x-filament::button>
        </div>
    </div>

    @if (empty($pagedRows))
        <div class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            Nenhum grupo encontrado no relatório.
        </div>
    @else
        <div class="space-y-4">
            @foreach ($pagedRows as $idx => $r)
                <div
                    class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                    wire:key="group-card-{{ $page }}-{{ $idx }}-{{ $r['id'] ?? $idx }}"
                >
                    {{-- ID --}}
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        ID do grupo
                    </div>
                    <div class="mt-1 break-all font-mono text-sm text-gray-900 dark:text-gray-100">
                        {{ $r['id'] ?? '-' }}
                    </div>

                    {{-- Nome + participantes --}}
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Nome do grupo
                            </div>
                            <div class="mt-1 break-words text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {{ $r['assunto'] ?? '-' }}
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            {{-- ✅ Participantes (número centralizado garantido com flex) --}}
                            <div class="w-36 rounded-xl border border-gray-200 bg-gray-50/80 px-3 py-2 dark:border-gray-700 dark:bg-gray-800/60">
                                <div class="text-center text-xs text-gray-500 dark:text-gray-400">
                                    Participantes
                                </div>

                                <div class="mt-1 flex items-center justify-center">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        {{ isset($r['membros']) ? number_format((int) $r['membros'], 0, ',', '.') : '-' }}
                                    </div>
                                </div>
                            </div>

                            {{--
                            <div class="rounded-xl border px-3 py-2">
                                <div class="text-xs text-gray-500">Criação (GMT-3)</div>
                                <div class="text-sm font-semibold text-gray-900 whitespace-nowrap">
                                    {{ $r['criacao'] ?? '-' }}
                                </div>
                            </div>
                            --}}
                        </div>
                    </div>

                    {{-- Descrição (colapsável) --}}
                    @if (!empty($r['descricao']))
                        <div class="mt-4">
                            <details class="rounded-xl border border-gray-200 bg-gray-50/80 p-3 dark:border-gray-700 dark:bg-gray-800/60">
                                <summary class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Ver descrição
                                </summary>
                                <div class="mt-2 whitespace-pre-wrap break-words text-sm text-gray-900 dark:text-gray-100">
                                    {{ $r['descricao'] }}
                                </div>
                            </details>
                        </div>
                    @endif

                    <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                        Use o <span class="font-mono">ID do grupo</span> para solicitar participantes via ofício.
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
