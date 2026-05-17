<div>
    @if (empty($rows))
        <div class="flex flex-col items-center justify-center py-12 text-gray-400 dark:text-gray-600">
            <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-10 w-10 mb-3 opacity-40" />
            <p class="text-sm">Nenhum evento encontrado para este período.</p>
        </div>
    @else
        <div class="mb-4 flex items-center gap-2">
            <x-filament::badge color="danger" size="lg">
                {{ count($rows) }} conexões
            </x-filament::badge>
            <span class="text-sm text-gray-500 dark:text-gray-400">nesta hora — ordenadas cronologicamente</span>
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Data/Hora (GMT-3)</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">IP</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Porta</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Operadora / ISP</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cidade</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                    @foreach ($rows as $row)
                        <tr
                            x-data="{ hovered: false }"
                            @mouseenter="hovered = true"
                            @mouseleave="hovered = false"
                            :style="hovered ? 'background-color: rgba(59,130,246,0.18)' : ''"
                            style="transition: background-color 0.12s ease;"
                        >
                            <td class="px-4 py-2.5 font-mono text-xs text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                {{ $row['datetime'] ?? '-' }}
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <x-filament::badge color="gray" size="sm">
                                    {{ $row['ip'] ?? '-' }}
                                </x-filament::badge>
                            </td>
                            <td class="px-4 py-2.5 font-mono text-xs text-gray-500 dark:text-gray-400">
                                {{ $row['port'] ?? '-' }}
                            </td>
                            <td class="px-4 py-2.5 text-xs text-gray-700 dark:text-gray-300">
                                {{ $row['provider'] ?? '-' }}
                            </td>
                            <td class="px-4 py-2.5 text-xs text-gray-700 dark:text-gray-300">
                                {{ $row['city'] ?? '-' }}
                            </td>
                            <td class="px-4 py-2.5">
                                <x-filament::badge
                                    :color="($row['type'] ?? '') === 'Móvel' ? 'warning' : 'gray'"
                                    size="sm"
                                >
                                    {{ $row['type'] ?? '-' }}
                                </x-filament::badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
