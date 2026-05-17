@php
    $names = array_values((array) ($names ?? []));
@endphp

<div class="space-y-3">
    <div class="border-b border-gray-200 pb-2 dark:border-gray-700">
        <div class="font-semibold text-gray-900 dark:text-gray-100">
            {{ $title ?? 'Contas' }}
        </div>
        <div class="text-xs text-gray-500 dark:text-gray-400">
            {{ number_format(count($names), 0, ',', '.') }} conta(s) encontrada(s)
        </div>
    </div>

    @if (count($names) === 0)
        <div class="text-sm text-gray-500 dark:text-gray-400">
            Nenhum nome encontrado nessa seção do log.
        </div>
    @else
        <div class="max-h-[65vh] overflow-y-auto rounded-xl border border-gray-200 bg-white divide-y divide-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:divide-gray-800">
            @foreach ($names as $name)
                <div class="px-4 py-3 text-sm text-gray-900 break-all dark:text-gray-100">
                    {{ $name }}
                </div>
            @endforeach
        </div>
    @endif
</div>
