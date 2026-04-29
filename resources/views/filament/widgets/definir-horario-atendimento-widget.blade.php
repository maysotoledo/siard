<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                    Horario de atendimento
                </h3>

                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Defina os horarios que aparecerao no menu de agendamento da sua agenda.
                </p>
            </div>

            <x-filament::button
                tag="a"
                :href="$this->getUrl()"
                icon="heroicon-o-clock"
            >
                Definir horario de atendimento
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
