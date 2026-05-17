<div class="space-y-6">

    <div>
        <div class="text-sm text-gray-500 mb-2">
            Total de eventos móveis:
            <span class="font-semibold">
                {{ number_format($report['mobile_total_events'] ?? 0, 0, ',', '.') }}
            </span>
        </div>
    </div>

    <div>
        <livewire:analise-inteligente.mobile-events-table
            :run-id="$runId"
            :wire:key="'mobile-' . $runId"
        />
    </div>

</div>
