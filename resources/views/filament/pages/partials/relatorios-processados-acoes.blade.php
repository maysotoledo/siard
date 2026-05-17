@php
    $record = $getRecord();
    $viewUrl = $this->resolveViewUrl($record);
@endphp

<div class="flex items-center gap-2">
    <x-filament::button
        tag="a"
        :href="$viewUrl"
        size="xs"
        color="primary"
        icon="heroicon-o-eye"
    >
        Ver
    </x-filament::button>

    <x-filament::button
        type="button"
        size="xs"
        color="danger"
        icon="heroicon-o-trash"
        wire:click="deleteInvestigation({{ (int) $record->id }})"
        wire:confirm="Tem certeza que deseja excluir esta investigacao?"
    >
        Excluir
    </x-filament::button>
</div>
