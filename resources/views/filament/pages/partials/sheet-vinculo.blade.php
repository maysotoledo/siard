@php
    $rows = $rows ?? [];
    $page = max(1, (int) ($this->vinculoPage ?? 1));
    $perPage = max(1, (int) ($this->vinculoPerPage ?? 10));
    $total = count($rows);
    $lastPage = max(1, (int) ceil($total / $perPage));
    $page = min($page, $lastPage);
    $pagedRows = array_slice($rows, ($page - 1) * $perPage, $perPage);
@endphp

<div class="space-y-4">
    @forelse($pagedRows as $row)
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="font-mono text-base font-semibold text-gray-950 dark:text-gray-100">{{ $row['ip'] ?? '-' }}</div>
                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        {{ $row['provider'] ?? '-' }} · {{ $row['city'] ?? '-' }} · {{ $row['type'] ?? '-' }}
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full bg-primary-50 px-2 py-1 font-medium text-primary-700 dark:bg-primary-500/20 dark:text-primary-200">
                        {{ number_format((int) ($row['targets_count'] ?? 0), 0, ',', '.') }} alvos
                    </span>
                    <span class="rounded-full bg-gray-100 px-2 py-1 font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        {{ number_format((int) ($row['total_occurrences'] ?? 0), 0, ',', '.') }} acessos
                    </span>
                    <span class="rounded-full bg-gray-100 px-2 py-1 font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        Último: {{ $row['last_seen'] ?? '-' }}
                    </span>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full min-w-[1120px] table-fixed divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <colgroup>
                        <col style="width: 420px;">
                        <col style="width: 160px;">
                        <col style="width: 270px;">
                        <col style="width: 270px;">
                    </colgroup>

                    <thead class="bg-gray-50 dark:bg-gray-800/80">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Alvo</th>
                            <th class="border-l border-gray-200 px-6 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-600 dark:border-gray-700 dark:text-gray-300">Acessos</th>
                            <th class="border-l border-gray-200 px-8 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:border-gray-700 dark:text-gray-300">Primeiro acesso</th>
                            <th class="border-l border-gray-200 px-8 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:border-gray-700 dark:text-gray-300">Último acesso</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @foreach(($row['accesses'] ?? []) as $access)
                            @php
                                $target = trim((string) ($access['target'] ?? ''));
                                $target = $target !== '' ? $target : 'Alvo não identificado';
                                $isSelected = (bool) ($access['is_selected'] ?? false);
                            @endphp

                            <tr @class([
                                'text-gray-800 transition-colors hover:bg-primary-50/60 dark:text-gray-200 dark:hover:bg-primary-500/10',
                                'bg-primary-50/70 dark:bg-primary-500/15' => $isSelected,
                            ])>
                                <td class="px-6 py-3 align-middle">
                                    <button
                                        type="button"
                                        class="-mx-2 inline-flex max-w-full rounded-md px-2 py-1 text-left font-semibold text-primary-600 transition-colors hover:bg-primary-100 hover:text-primary-800 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-white dark:text-primary-300 dark:hover:bg-primary-500/20 dark:hover:text-primary-100 dark:focus:ring-offset-gray-900"
                                        title="{{ $target }}"
                                        wire:click="openVinculoTimesModal(@js($row['ip'] ?? ''), @js($target))"
                                    >
                                        <span class="truncate">{{ $target }}</span>
                                    </button>
                                    
                                </td>
                                <td class="border-l border-gray-100 px-6 py-3 text-center tabular-nums align-middle dark:border-gray-800">
                                    <span class="inline-flex min-w-12 justify-center rounded-md bg-gray-100 px-2 py-1 font-semibold text-gray-800 dark:bg-gray-800 dark:text-gray-100">
                                        {{ number_format((int) ($access['count'] ?? 0), 0, ',', '.') }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap border-l border-gray-100 px-8 py-3 align-middle font-mono text-xs text-gray-700 dark:border-gray-800 dark:text-gray-300">
                                    {{ $access['first_seen'] ?? '-' }}
                                </td>
                                <td class="whitespace-nowrap border-l border-gray-100 px-8 py-3 align-middle font-mono text-xs text-gray-700 dark:border-gray-800 dark:text-gray-300">
                                    {{ $access['last_seen'] ?? '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="rounded-lg border border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            Nenhum IP compartilhado entre alvos diferentes nesta investigação.
        </div>
    @endforelse

    @if($total > $perPage)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="text-gray-600 dark:text-gray-300">
                Mostrando
                <span class="font-semibold">{{ number_format((($page - 1) * $perPage) + 1, 0, ',', '.') }}</span>
                a
                <span class="font-semibold">{{ number_format(min($page * $perPage, $total), 0, ',', '.') }}</span>
                de
                <span class="font-semibold">{{ number_format($total, 0, ',', '.') }}</span>
                vínculos
            </div>

            <div class="flex items-center gap-2">
                <x-filament::button
                    type="button"
                    size="sm"
                    color="gray"
                    :disabled="$page <= 1"
                    wire:click="setVinculoPage({{ $page - 1 }})"
                >
                    Anterior
                </x-filament::button>

                <span class="px-2 text-gray-600 dark:text-gray-300">
                    Página {{ number_format($page, 0, ',', '.') }} de {{ number_format($lastPage, 0, ',', '.') }}
                </span>

                <x-filament::button
                    type="button"
                    size="sm"
                    color="gray"
                    :disabled="$page >= $lastPage"
                    wire:click="setVinculoPage({{ $page + 1 }})"
                >
                    Próxima
                </x-filament::button>
            </div>
        </div>
    @endif
</div>
