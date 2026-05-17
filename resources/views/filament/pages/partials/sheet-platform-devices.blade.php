<div class="space-y-6">
    <x-filament::section heading="User-Agents">
        @livewire('analise-inteligente.' . ($componentPrefix ?? 'generic') . '-user-agents-table', ['runId' => $runId], key('platform-user-agents-' . ($componentPrefix ?? 'generic') . '-' . $runId))
    </x-filament::section>

    <x-filament::section heading="Identificadores de Dispositivo">
        @livewire('analise-inteligente.' . ($componentPrefix ?? 'generic') . '-device-identifiers-table', ['runId' => $runId], key('platform-device-identifiers-' . ($componentPrefix ?? 'generic') . '-' . $runId))
    </x-filament::section>
</div>
