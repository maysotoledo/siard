<div class="space-y-6">

    <div>
        <div class="text-sm text-gray-500 mb-2">
            Total de eventos noturnos:
            <span class="font-semibold">
                {{ number_format($report['night_total_events'] ?? 0, 0, ',', '.') }}
            </span>
        </div>
    </div>

    <div>
        <livewire:analise-inteligente.night-events-table
            :run-id="$runId"
            :wire:key="'night-' . $runId"
        />
    </div>

</div>
