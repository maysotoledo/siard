<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div style="display:flex;align-items:center;gap:8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                Atualização do Sistema
            </div>
        </x-slot>
        <x-slot name="description">
            Configure o aviso de manutenção exibido na página inicial para os usuários.
        </x-slot>

        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button type="submit" color="primary">
                    Salvar configuração
                </x-filament::button>
            </div>
        </form>

        <x-filament-actions::modals />
    </x-filament::section>
</x-filament-widgets::widget>
