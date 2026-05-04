@php
    /** @var string $titulo */
    /** @var ?string $descricao */
    /** @var \Illuminate\Support\Collection $linhas */
    $badgeColors = [
        'success' => 'background-color: rgba(34,197,94,.12); color: rgb(22 163 74);',
        'danger' => 'background-color: rgba(239,68,68,.12); color: rgb(220 38 38);',
        'warning' => 'background-color: rgba(245,158,11,.15); color: rgb(180 83 9);',
        'info' => 'background-color: rgba(59,130,246,.12); color: rgb(37 99 235);',
        'primary' => 'background-color: rgba(245,158,11,.15); color: rgb(180 83 9);',
        'gray' => 'background-color: rgba(107,114,128,.15); color: rgb(75 85 99);',
    ];
@endphp

<div class="space-y-3 text-sm">
    @if (! empty($descricao))
        <div class="text-gray-600 dark:text-gray-300">
            {{ $descricao }}
        </div>
    @endif

    @if ($linhas->isEmpty())
        <p class="text-gray-500 dark:text-gray-400">
            Nenhum registro para exibir.
        </p>
    @else
        <ul class="divide-y divide-gray-200 dark:divide-gray-700 rounded-md border border-gray-200 dark:border-gray-700">
            @foreach ($linhas as $linha)
                <li class="flex flex-col gap-0.5 px-3 py-2">
                    <div class="flex items-start justify-between gap-2">
                        <span class="font-medium text-gray-900 dark:text-gray-100">
                            {{ $linha['nome'] ?? '-' }}
                        </span>

                        @if (! empty($linha['badge']))
                            @php $cor = $badgeColors[$linha['badgeColor'] ?? 'gray'] ?? $badgeColors['gray']; @endphp
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap"
                                style="{{ $cor }}"
                            >
                                {{ $linha['badge'] }}
                            </span>
                        @endif
                    </div>

                    @if (! empty($linha['sub']))
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $linha['sub'] }}
                        </span>
                    @endif

                    @if (! empty($linha['meta']))
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $linha['meta'] }}
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="text-xs text-gray-500 dark:text-gray-400">
            Total: {{ $linhas->count() }}
        </div>
    @endif
</div>
