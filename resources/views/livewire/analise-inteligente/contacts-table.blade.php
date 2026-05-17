<div class="space-y-3">
    <div class="text-sm text-gray-500 dark:text-gray-400">
        Quantidade:
        <span class="font-semibold text-gray-800 dark:text-gray-100">{{ number_format(count($contacts ?? []), 0, ',', '.') }}</span>
        {{-- <span class="ml-2 text-xs text-gray-400">Dica: selecione o número e copie manualmente (Ctrl+C / Cmd+C).</span> --}}
    </div>

    {{ $this->table }}
</div>
