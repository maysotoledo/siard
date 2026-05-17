<div>
    <livewire:analise-inteligente.provider-ips-table
        :rows="$rows"
        :wire:key="'provider-ips-' . md5(json_encode($rows))"
    />
</div>
