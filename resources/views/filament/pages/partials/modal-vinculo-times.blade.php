@php
    $times = $times ?? [];
@endphp

<div class="space-y-4">
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
        <div><span class="font-semibold">Alvo:</span> {{ $target ?? '-' }}</div>
        <div><span class="font-semibold">IP:</span> <span class="font-mono">{{ $ip ?? '-' }}</span></div>
        <div><span class="font-semibold">Total:</span> {{ number_format(count($times), 0, ',', '.') }} horário(s)</div>
    </div>

    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($times as $time)
            <div class="rounded border border-gray-200 bg-white px-3 py-2 font-mono text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                {{ $time }}
            </div>
        @empty
            <div class="rounded border border-gray-200 bg-white px-3 py-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 sm:col-span-2 lg:col-span-3">
                Nenhum horário disponível para este vínculo.
            </div>
        @endforelse
    </div>
</div>
