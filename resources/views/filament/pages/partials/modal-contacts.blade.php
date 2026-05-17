<livewire:analise-inteligente.contacts-table
    :contacts="$contacts ?? []"
    :wire:key="'contacts-table-' . md5(json_encode($contacts ?? []))"
/>
