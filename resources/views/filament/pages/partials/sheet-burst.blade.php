@php
    $maxCount = collect($rows)->max('count') ?: 1;
@endphp

@if (empty($rows))
    <div class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-600">
        <x-filament::icon icon="heroicon-o-bolt" class="h-10 w-10 mb-3 opacity-40" />
        <p class="text-sm">Nenhum evento registrado.</p>
    </div>
@else
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 w-48">
                        Hora (GMT-3)
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Conexões
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Intensidade
                    </th>
                    <th class="px-4 py-3 w-10"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                @foreach ($rows as $row)
                    @php
                        $pct  = round(($row['count'] / $maxCount) * 100);
                        $isBurst = $row['count'] >= 20;
                        $barColor = $isBurst
                            ? 'bg-red-500'
                            : ($row['count'] >= 10 ? 'bg-yellow-400' : 'bg-blue-400');
                    @endphp
                    <tr
                        wire:click="openBurstModal('{{ $row['burst_hour'] }}')"
                        x-data="{ hovered: false }"
                        @mouseenter="hovered = true"
                        @mouseleave="hovered = false"
                        :style="hovered ? 'background-color: rgba(59,130,246,0.15); cursor:pointer;' : 'cursor:pointer;'"
                        style="transition: background-color 0.1s ease;"
                        title="Ver IPs desta hora"
                    >
                        <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-200 whitespace-nowrap">
                            {{ $row['label'] }}h
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if ($isBurst)
                                <x-filament::badge color="danger">{{ $row['count'] }}</x-filament::badge>
                            @elseif ($row['count'] >= 10)
                                <x-filament::badge color="warning">{{ $row['count'] }}</x-filament::badge>
                            @else
                                <x-filament::badge color="gray">{{ $row['count'] }}</x-filament::badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 w-full">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-2 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                                    <div
                                        class="h-full rounded-full {{ $barColor }}"
                                        style="width: {{ $pct }}%"
                                    ></div>
                                </div>
                                <span class="text-xs text-gray-400 dark:text-gray-500 w-8 text-right">{{ $pct }}%</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-400 dark:text-gray-500">
                            <x-filament::icon icon="heroicon-o-chevron-right" class="h-4 w-4" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
