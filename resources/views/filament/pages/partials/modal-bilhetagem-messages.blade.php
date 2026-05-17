@php
    $lastPage = $lastPage ?? 1;
    $page = $page ?? 1;
    $total = $total ?? 0;
@endphp

<div class="space-y-4">
    <div class="flex items-start justify-between gap-3">
        <div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Contato</div>
            <div class="font-semibold text-gray-950 dark:text-gray-100">{{ $contactName ?? 'Desconhecido' }}</div>
            <div class="break-all font-mono text-sm text-gray-700 dark:text-gray-300">{{ $phone ?? '-' }}</div>
        </div>

        <div class="text-sm text-gray-500 dark:text-gray-400">
            Total: <span class="font-semibold">{{ number_format($total, 0, ',', '.') }}</span>
        </div>
    </div>

    {{-- ✅ Cards --}}
    <div class="grid gap-3">
        @forelse($rows as $r)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="grid gap-2">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Timestamp (GMT-3)</div>
                        <div class="text-sm font-semibold text-gray-950 dark:text-gray-100">{{ $r['timestamp'] ?? '-' }}</div>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <div class="text-xs text-gray-500 dark:text-gray-400">IP / Porta</div>
                        <div class="break-all font-mono text-sm text-gray-950 dark:text-gray-100">
                            {{ $r['sender_ip'] ?? '-' }}:{{ $r['sender_port'] ?? '-' }}
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Provedor (IP)</div>
                        <div class="text-sm font-semibold text-gray-950 dark:text-gray-100">{{ $r['sender_provider'] ?? '-' }}</div>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Tipo</div>
                        <div class="text-sm font-semibold text-gray-950 dark:text-gray-100">{{ $r['type'] ?? '-' }}</div>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Message Id</div>
                        <div class="break-all font-mono text-xs text-gray-800 dark:text-gray-200">{{ $r['message_id'] ?? '-' }}</div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Nenhuma mensagem encontrada.
            </div>
        @endforelse
    </div>

    {{-- ✅ Paginação --}}
    <div class="flex items-center justify-between gap-3">
        <div class="text-xs text-gray-500 dark:text-gray-400">
            Página {{ $page }} de {{ $lastPage }}
        </div>

        <div class="flex items-center gap-2">
            <x-filament::button
                type="button"
                size="sm"
                color="gray"
                wire:click="bilhetagemModalPrevPage"
                :disabled="$page <= 1"
            >
                Anterior
            </x-filament::button>

            <x-filament::button
                type="button"
                size="sm"
                color="gray"
                wire:click="bilhetagemModalNextPage"
                :disabled="$page >= $lastPage"
            >
                Próxima
            </x-filament::button>
        </div>
    </div>
</div>
